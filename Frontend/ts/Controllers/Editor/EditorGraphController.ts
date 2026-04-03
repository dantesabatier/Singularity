import {ApplicationContext} from "@/Application/ApplicationContext"

type GraphAttribute = {
    name: string
    type: string
}

type GraphNodeData = {
    id: string
    label: string
    href?: string
    entityID?: string
    type?: string
    attributes?: GraphAttribute[]
    height?: number
}

type GraphNode = {
    data: GraphNodeData
    position?: { x: number, y: number }
}

type GraphEdge = Record<string, unknown>

type GraphModel = {
    id?: string
    zoom?: number
    pan?: { x: number, y: number }
    nodes?: GraphNode[]
    edges?: GraphEdge[]
}

type CytoscapeNode = {
    data(name: string): string | undefined
    position(): { x: number, y: number }
}

type CytoscapeInstance = {
    zoom(value?: number): number
    pan(): { x: number, y: number }
    fit(padding?: number): void
    center(): void
    resize(): void
    layout(options: Record<string, unknown>): { run(): void }
    nodes(): Array<{
        data(name: string): string | undefined
        position(axis: "x" | "y"): number
    }>
    on(event: string, selectorOrHandler: string | ((event: { target: CytoscapeNode }) => void), handler?: (event: { target: CytoscapeNode }) => void): void
    container(): HTMLElement
    nodeHtmlLabel?(options: Array<Record<string, unknown>>): void
}

export class EditorGraphController {
    private cy: CytoscapeInstance | null = null
    private graphInitialized = false
    private currentGraphCanvas: HTMLElement | null = null
    private readonly resizeObserver = new ResizeObserver(() => {
        this.cy?.resize()
    })
    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element) || !this.supportsCurrentView()) {
            return
        }
        const segmentButton = target.closest<HTMLButtonElement>("#interactive-switcher .segmented-control-button")
        if (segmentButton) {
            void this.handleSegmentedNavigation(segmentButton)
            return
        }
        const actionButton = target.closest<HTMLButtonElement>("[data-graph-action]")
        if (!actionButton || !this.cy) {
            return
        }
        const action = actionButton.dataset.graphAction
        if (!action) {
            return
        }
        switch (action) {
            case "zoomIn":
                this.cy.zoom(this.cy.zoom() * 1.2)
                this.cy.center()
                this.updateZoom(this.graphModel)
                return
            case "zoomOut":
                this.cy.zoom(this.cy.zoom() * 0.8)
                this.cy.center()
                this.updateZoom(this.graphModel)
                return
            case "fit":
                this.cy.fit(50)
                this.updateZoom(this.graphModel)
                return
            case "center":
                this.cy.center()
                return
            case "layout":
                this.layoutGraph(this.graphModel)
                return
            default:
                return
        }
    }
    private graphModel: GraphModel = {}
    private saveTimer: number | null = null

    public constructor(private readonly context: ApplicationContext) {
        document.addEventListener("click", this.onDocumentClick)
    }

    public initialize(): void {
        if (!this.supportsCurrentView()) {
            return
        }
        this.syncGraphLifecycle()
        this.graphModel = this.readGraphModel()
        this.updateEmptyState()
        this.observeGraphContainer()
        const isGraphViewSelected = document.querySelector('.segmented-control-button[data-target="graphView"]')?.classList.contains("active")
        if (isGraphViewSelected) {
            window.requestAnimationFrame(() => this.initializeGraph())
        }
    }

    private supportsCurrentView(): boolean {
        return document.getElementById("interactive-switcher") !== null
    }

    private async handleSegmentedNavigation(button: HTMLButtonElement): Promise<void> {
        if (button.classList.contains("active")) {
            return
        }
        const target = button.dataset.target
        if (!target) {
            return
        }
        await this.context.actionDispatcher.dispatch("Synchronize", {editorSelectedView: target})
    }

    private readGraphModel(): GraphModel {
        const currentGraphData = document.getElementById("current-graph-data")
        if (!(currentGraphData instanceof HTMLElement)) {
            return {}
        }
        const raw = currentGraphData.dataset.json ?? "{}"
        try {
            const model = JSON.parse(raw) as GraphModel
            model.nodes?.forEach((node) => {
                node.data.height = this.calculateNodeHeight(node.data.attributes)
            })
            return model
        } catch {
            return {}
        }
    }

    private calculateNodeHeight(attributes: GraphAttribute[] | undefined): number {
        if (!attributes || attributes.length === 0) {
            return 60
        }
        const headerHeight = 28
        const rowHeight = 24
        const maxAttributes = 10
        const visibleRows = Math.min(attributes.length, maxAttributes)
        const overflowHeight = attributes.length > maxAttributes ? rowHeight : 0
        return headerHeight + (visibleRows * rowHeight) + overflowHeight + 2
    }

    private updateEmptyState(): void {
        const emptyState = document.getElementById("graph-empty-state")
        if (!(emptyState instanceof HTMLElement)) {
            return
        }
        const hasNodes = Array.isArray(this.graphModel.nodes) && this.graphModel.nodes.length > 0
        emptyState.style.display = hasNodes ? "none" : "block"
    }

    private observeGraphContainer(): void {
        const container = document.getElementById("graph-view-container")
        if (!(container instanceof HTMLElement)) {
            return
        }
        this.resizeObserver.disconnect()
        this.resizeObserver.observe(container)
    }

    private initializeGraph(): void {
        if (this.graphInitialized) {
            return
        }
        const container = document.getElementById("graph-canvas")
        if (!(container instanceof HTMLElement) || container.offsetWidth === 0 || container.offsetHeight === 0) {
            return
        }
        const nodes = this.graphModel.nodes ?? []
        const edges = this.graphModel.edges ?? []
        const elements: Array<GraphNode | GraphEdge> = [...nodes, ...edges]
        const hasSavedPositions = elements.some((element) => "position" in element && element.position !== undefined)
        const layout = hasSavedPositions
            ? {name: "preset"}
            : {
                name: "dagre",
                rankDir: "TB",
                nodeSep: 80,
                rankSep: 100,
                padding: 50,
                animate: false,
                fit: false,
            }
        this.cy = window.cytoscape({
            container,
            elements,
            zoom: this.graphModel.zoom ?? 0.1,
            pan: this.graphModel.pan ?? {x: 0, y: 0},
            minZoom: 0.1,
            maxZoom: 3,
            style: this.graphStyle,
            layout,
        }) as unknown as CytoscapeInstance
        window.cy = this.cy
        this.registerNodeHtmlLabel()
        this.bindGraphEvents()
        this.updateZoomLabel(this.cy.zoom())
        this.graphInitialized = true
    }

    private syncGraphLifecycle(): void {
        const graphCanvas = document.getElementById("graph-canvas")
        if (!(graphCanvas instanceof HTMLElement)) {
            this.currentGraphCanvas = null
            this.graphInitialized = false
            this.cy = null
            window.cy = undefined
            return
        }
        if (this.currentGraphCanvas === graphCanvas) {
            return
        }
        this.currentGraphCanvas = graphCanvas
        this.graphInitialized = false
        this.cy = null
        window.cy = undefined
    }

    private get graphStyle(): Array<Record<string, unknown>> {
        return [
            {
                selector: "node",
                style: {
                    shape: "rectangle",
                    width: 200,
                    height: "data(height)",
                    "background-opacity": 0,
                    "border-width": 0,
                },
            },
            {
                selector: "edge",
                style: {
                    width: 2,
                    "curve-style": "taxi",
                    "taxi-direction": "vertical",
                    "taxi-turn": 20,
                    "line-color": "#6c757d",
                    "target-arrow-color": "#6c757d",
                    "source-arrow-color": "#6c757d",
                    "arrow-scale": 1.2,
                    color: "#adb5bd",
                    "font-size": 10,
                    "text-background-color": "#1e1f22",
                    "text-background-opacity": 0.8,
                    "text-background-padding": 2,
                    label: "",
                },
            },
            {
                selector: "edge[type=\"inheritance\"]",
                style: {
                    "target-arrow-shape": "triangle",
                    "target-arrow-fill": "hollow",
                    "line-style": "dashed",
                    "line-color": "#c7a651",
                    "target-arrow-color": "#c7a651",
                },
            },
            {
                selector: "edge[type=\"relationship\"]",
                style: {
                    "target-arrow-shape": "none",
                },
            },
            {
                selector: "edge[type=\"relationship\"][?isToMany]",
                style: {
                    "target-arrow-shape": "triangle",
                },
            },
            {
                selector: "edge[type=\"relationship\"][?inverseIsToMany]",
                style: {
                    "source-arrow-shape": "triangle",
                },
            },
        ]
    }

    private registerNodeHtmlLabel(): void {
        if (!this.cy?.nodeHtmlLabel) {
            return
        }
        this.cy.nodeHtmlLabel([{
            query: "node",
            halign: "center",
            valign: "center",
            halignBox: "center",
            valignBox: "center",
            tpl: (data: GraphNodeData): string => this.renderNodeHtml(data),
        }])
    }

    private renderNodeHtml(data: GraphNodeData): string {
        const attributes = data.attributes ?? []
        const totalHeight = data.height ?? 60
        const borderColor = data.type === "abstract" ? "#c7a651" : "#5a98d7"
        const headerBackground = data.type === "abstract" ? "#3a3530" : "#313335"
        let html = `<div style="width:200px; height:${totalHeight}px; background:#2b2d30; border:1px solid ${borderColor}; border-radius:4px; overflow:hidden; display:flex; flex-direction:column; box-sizing:border-box;">`
        html += `<div style="height:28px; background:${headerBackground}; color:#e8eaed; font-weight:600; padding:0 8px; display:flex; align-items:center; border-bottom:1px solid ${borderColor}; font-size:12px;">${data.label ?? data.id}</div>`
        html += "<div style=\"flex:1; display:flex; flex-direction:column; overflow:hidden;\">"
        if (attributes.length === 0) {
            html += "<div style=\"flex:1; display:flex; align-items:center; justify-content:center; color:#868991; font-style:italic; font-size:11px;\">No attributes</div>"
            html += "</div></div>"
            return html
        }
        const maxAttributes = 10
        const visibleAttributes = attributes.slice(0, maxAttributes)
        visibleAttributes.forEach((attribute) => {
            html += "<div style=\"height:24px; display:flex; justify-content:space-between; align-items:center; padding:0 8px; border-bottom:1px solid #3d3f41; font-size:11px;\">"
            html += `<span style="color:#bcbec4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${attribute.name}</span>`
            html += `<span style="color:#868991; font-size:10px; margin-left:8px;">${attribute.type}</span>`
            html += "</div>"
        })
        if (attributes.length > maxAttributes) {
            html += `<div style="height:24px; display:flex; justify-content:center; align-items:center; color:#868991; font-style:italic; font-size:10px;">+ ${attributes.length - maxAttributes} more</div>`
        }
        html += "</div></div>"
        return html
    }

    private bindGraphEvents(): void {
        if (!this.cy) {
            return
        }
        this.cy.on("zoom pan", () => this.updateZoom(this.graphModel))
        this.cy.on("mouseover", "node", () => {
            this.cy?.container().style.setProperty("cursor", "pointer")
        })
        this.cy.on("mouseout", "node", () => {
            this.cy?.container().style.setProperty("cursor", "default")
        })
        this.cy.on("tap", "node", (event) => {
            const href = event.target.data("href")
            if (!href) {
                return
            }
            void this.context.viewNavigator.push(window.location.origin + href)
        })
        this.cy.on("dragfree", "node", (event) => {
            void this.saveNodePosition(event.target)
        })
    }

    private updateZoom(model: GraphModel): void {
        if (!this.cy) {
            return
        }
        this.updateZoomLabel(this.cy.zoom())
        if (this.saveTimer !== null) {
            window.clearTimeout(this.saveTimer)
        }
        this.saveTimer = window.setTimeout(() => {
            void this.context.httpClient.request("Model", {
                method: "PATCH",
                body: {
                    objectID: model.id,
                    zoom: this.cy?.zoom(),
                    pan: this.cy?.pan(),
                },
            })
        }, 500)
    }

    private updateZoomLabel(zoom: number): void {
        const label = document.getElementById("graph-zoom-level")
        if (!(label instanceof HTMLElement)) {
            return
        }
        label.innerText = `${Math.round(zoom * 100)}%`
    }

    private async saveNodePosition(node: CytoscapeNode): Promise<void> {
        const entityID = node.data("entityID")
        if (!entityID) {
            return
        }
        const position = node.position()
        await this.context.httpClient.request("Entity", {
            method: "PATCH",
            body: {
                objectID: entityID,
                position: {
                    x: position.x,
                    y: position.y,
                },
            },
        })
    }

    private layoutGraph(model: GraphModel): void {
        if (!this.cy) {
            return
        }
        this.cy.layout({
            name: "dagre",
            rankDir: "TB",
            nodeSep: 80,
            rankSep: 100,
            padding: 50,
            animate: true,
            stop: () => {
                const entities = this.cy?.nodes().map((node) => ({
                    objectID: node.data("entityID"),
                    name: node.data("label"),
                    position: {
                        x: node.position("x"),
                        y: node.position("y"),
                    },
                })) ?? []
                if (entities.length === 0) {
                    return
                }
                void this.context.httpClient.request("Model", {
                    method: "PATCH",
                    body: {
                        objectID: model.id,
                        zoom: this.cy?.zoom(),
                        pan: this.cy?.pan(),
                        entities,
                    },
                })
            },
        }).run()
    }
}
