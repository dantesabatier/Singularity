import {ViewController} from "@/Application/ViewController"

type DBViewerController = {
    getZoom(): number
    setZoom(zoom: number): void
    centerView(): void
}

type DBViewerElement = HTMLElement & {
    schema: unknown
    viewer?: DBViewerController
    shadowRoot: ShadowRoot | null
}

type ViewerRuntime = {
    projectID: string
    schema: unknown
    exportLastDirectory: string
}

export class ViewerController extends ViewController {
    private currentViewer: DBViewerController | null = null
    private exportModalInstance: InstanceType<BootstrapStatic["Modal"]> | null = null
    private selectedExportDirectory = ""
    private readonly initializedViewers = new WeakSet<DBViewerElement>()
    private readonly themedViewers = new WeakSet<DBViewerElement>()
    private readonly onActionClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const element = target.closest<HTMLElement>("[data-viewer-action]")
        if (!element || !this.supportsCurrentView()) {
            return
        }
        const action = element.dataset.viewerAction
        switch (action) {
            case "zoomIn":
                this.zoomIn()
                return
            case "zoomOut":
                this.zoomOut()
                return
            case "resetZoom":
                this.resetZoom()
                return
            case "centerView":
                this.centerView()
                return
            case "showExportModal":
                this.showExportModal()
                return
            case "chooseExportFolder":
                void this.chooseExportFolder()
                return
            case "confirmExport":
                void this.confirmExport()
                return
            case "showAboutPanel":
                void this.context.desktopBridge.showAboutPanel()
                return
            default:
                return
        }
    }

    protected override supportsCurrentView(): boolean {
        return document.querySelector("db-viewer") !== null
    }

    protected override setup(): void {
        document.addEventListener("click", this.onActionClick)
        this.initializeViewer()
    }

    protected override viewDidUpdate(): void {
        this.initializeViewer()
    }

    private initializeViewer(): void {
        const viewer = document.querySelector("db-viewer")
        if (!(viewer instanceof HTMLElement)) {
            return
        }
        const element = viewer as DBViewerElement
        const runtime = this.runtime
        this.selectedExportDirectory = runtime.exportLastDirectory
        this.updateExportDirectoryInput(runtime.exportLastDirectory)
        this.applyTheme(element)
        this.ensureShadowTheme(element)
        element.schema = runtime.schema
        this.updateEmptyState(this.isSchemaEmpty(runtime.schema))
        if (element.viewer) {
            this.currentViewer = element.viewer
            this.currentViewer.setZoom(0.25)
            this.updateZoomLevel(0.25)
        }
        if (this.initializedViewers.has(element)) {
            return
        }
        element.addEventListener("ready", (event) => {
            const target = event.target as DBViewerElement
            this.currentViewer = target.viewer ?? null
            setTimeout(() => {
                this.applyTheme(target)
                this.ensureShadowTheme(target)
            }, 100)
            if (this.currentViewer) {
                this.currentViewer.setZoom(0.25)
                this.updateZoomLevel(0.25)
            }
        })
        element.addEventListener("tableMoveEnd", (event) => {
            const detail = (event as CustomEvent<Record<string, unknown>>).detail
            void this.context.actionDispatcher.dispatch("Moved", {
                ...detail,
                project: runtime.projectID,
            })
        })
        this.initializedViewers.add(element)
    }

    private get runtime(): ViewerRuntime {
        const element = document.getElementById("viewer-runtime")
        if (!(element instanceof HTMLElement)) {
            return {
                projectID: "",
                schema: [],
                exportLastDirectory: "",
            }
        }
        const schemaElement = document.getElementById("viewer-schema-json")
        const rawSchema = schemaElement?.textContent?.trim() ?? "[]"
        let schema: unknown = []
        try {
            schema = JSON.parse(rawSchema)
            if (typeof schema === "string") {
                schema = JSON.parse(schema)
            }
        } catch {
            schema = []
        }
        return {
            projectID: element.dataset.projectId ?? "",
            schema,
            exportLastDirectory: element.dataset.exportLastDirectory ?? "",
        }
    }

    private isSchemaEmpty(schema: unknown): boolean {
        if (schema === null || schema === undefined) {
            return true
        }
        if (Array.isArray(schema)) {
            return schema.length === 0
        }
        if (typeof schema === "object") {
            return Object.keys(schema as Record<string, unknown>).length === 0
        }
        return false
    }

    private updateZoomLevel(zoom: number): void {
        const element = document.getElementById("viewer-zoom-level")
        if (!(element instanceof HTMLElement)) {
            return
        }
        element.innerText = `${Math.round(zoom * 100)}%`
    }

    private zoomIn(): void {
        if (!this.currentViewer) {
            return
        }
        const zoom = this.currentViewer.getZoom() * 1.2
        this.currentViewer.setZoom(zoom)
        this.updateZoomLevel(this.currentViewer.getZoom())
    }

    private zoomOut(): void {
        if (!this.currentViewer) {
            return
        }
        const zoom = this.currentViewer.getZoom() * 0.8
        this.currentViewer.setZoom(zoom)
        this.updateZoomLevel(this.currentViewer.getZoom())
    }

    private resetZoom(): void {
        if (!this.currentViewer) {
            return
        }
        this.currentViewer.setZoom(0.25)
        this.updateZoomLevel(0.25)
    }

    private centerView(): void {
        this.currentViewer?.centerView()
    }

    private showExportModal(): void {
        const element = document.getElementById("exportModal")
        if (!(element instanceof HTMLElement)) {
            return
        }
        if (!this.exportModalInstance) {
            this.exportModalInstance = new window.bootstrap.Modal(element)
        }
        this.exportModalInstance.show()
    }

    private async chooseExportFolder(): Promise<void> {
        const directory = await this.context.desktopBridge.browse()
        if (!directory) {
            return
        }
        this.selectedExportDirectory = directory
        this.updateExportDirectoryInput(directory)
        await this.context.actionDispatcher.dispatch("Synchronize", {
            exportLastDirectory: directory,
        })
    }

    private async confirmExport(): Promise<void> {
        const runtime = this.runtime
        if (!this.selectedExportDirectory || !runtime.projectID) {
            return
        }
        await this.context.actionDispatcher.dispatch("Export", {
            directory: this.selectedExportDirectory,
            project: runtime.projectID,
        })
        this.exportModalInstance?.hide()
    }

    private updateExportDirectoryInput(value: string): void {
        const input = document.getElementById("exportLastDirectory")
        if (!(input instanceof HTMLInputElement)) {
            return
        }
        input.value = value
    }

    private updateEmptyState(isEmpty: boolean): void {
        const emptyState = document.getElementById("viewer-empty-state")
        if (!(emptyState instanceof HTMLElement)) {
            return
        }
        emptyState.style.display = isEmpty ? "block" : "none"
    }

    private applyTheme(viewer: DBViewerElement): void {
        const styles = window.getComputedStyle(document.documentElement)
        viewer.style.setProperty("--viewer-background-color", styles.getPropertyValue("--bg-primary").trim())
        viewer.style.setProperty("--relation-color", styles.getPropertyValue("--text-primary").trim())
        viewer.style.setProperty("--relation-color-highlight", styles.getPropertyValue("--accent-blue").trim())
        viewer.style.setProperty("--entity-background", styles.getPropertyValue("--bg-secondary").trim())
        viewer.style.setProperty("--entity-border", styles.getPropertyValue("--border-color").trim())
        viewer.style.setProperty("--entity-header-background", styles.getPropertyValue("--bg-tertiary").trim())
        viewer.style.setProperty("--entity-text", styles.getPropertyValue("--text-primary").trim())
        viewer.style.setProperty("--entity-text-secondary", styles.getPropertyValue("--text-secondary").trim())
    }

    private injectShadowTheme(viewer: DBViewerElement): void {
        if (!viewer.shadowRoot || viewer.shadowRoot.getElementById("singularity-viewer-theme")) {
            return
        }
        const style = document.createElement("style")
        style.id = "singularity-viewer-theme"
        style.textContent = `
            * { --color: #bcbec4 !important; --table-boarder-color: #3d3f41 !important; }
            #veiwer-container { background-color: #1e1f22 !important; }
            #veiwer { background-color: #1e1f22 !important; }
            #minimap { background-color: #2b2d30 !important; border-color: #3d3f41 !important; }
            .table { background-color: #2b2d30 !important; border-color: #3d3f41 !important; color: #bcbec4 !important; }
            .table tr th { background-color: #3d3f41 !important; color: #e8eaed !important; }
            .table tr { border-bottom-color: #4a4d51 !important; }
            .table td { color: #bcbec4 !important; background-color: transparent !important; }
            .tableGroup { stroke: #4a4d51 !important; }
            path { stroke: #868991 !important; stroke-width: 1 !important; }
            .pathHover { stroke: #4a88c7 !important; stroke-width: 2 !important; }
            .highlight { stroke-width: 12 !important; stroke: transparent !important; }
            .table td.status { background-color: transparent !important; }
            .table td.status .pk, .table td.status .fk { filter: invert(1) brightness(0.9) !important; opacity: 0.8 !important; }
            .table tr:hover td.status .pk, .table tr:hover td.status .fk { opacity: 1 !important; }
            .fromRelation { background-color: rgba(90, 156, 90, 0.2) !important; }
            .toRelation { background-color: rgba(199, 84, 80, 0.2) !important; }
            #minimap-container #btn-container { display: none !important; }
        `
        viewer.shadowRoot.appendChild(style)
    }

    private ensureShadowTheme(viewer: DBViewerElement): void {
        if (this.themedViewers.has(viewer)) {
            return
        }
        let attempts = 0
        const apply = (): void => {
            attempts += 1
            this.injectShadowTheme(viewer)
            if (viewer.shadowRoot?.getElementById("singularity-viewer-theme")) {
                this.themedViewers.add(viewer)
                return
            }
            if (attempts < 20) {
                window.setTimeout(apply, 50)
            }
        }
        apply()
    }
}
