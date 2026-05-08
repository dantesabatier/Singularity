import {ApplicationContext} from "@/Application/ApplicationContext"

type SplitPanel = "source" | "sidebar"

type SplitInstance = {
    getSizes(): [number, number, number]
    setSizes(sizes: [number, number, number]): void
    collapse(index: number): void
    destroy?(): void
}

export class EditorSplitViewController {
    private splitInstance: SplitInstance | null = null
    private isCopilotEnabled = false
    private currentContainer: HTMLElement | null = null
    private sidebarTabAbortController: AbortController | null = null

    public constructor(private readonly context: ApplicationContext) {
    }

    public initialize(): void {
        const container = document.getElementById("split-container")
        if (!(container instanceof HTMLElement)) {
            return
        }
        this.isCopilotEnabled = container.dataset.copilotEnabled === "true"
        this.updateSidebarMode()
        this.sidebarTabAbortController?.abort()
        this.sidebarTabAbortController = new AbortController()
        const signal = this.sidebarTabAbortController.signal
        document.querySelectorAll<HTMLButtonElement>("[data-sidebar-tab]").forEach(btn => {
            btn.addEventListener("click", () => {
                const target = btn.dataset.sidebarTab
                if (target === "inspector" || target === "chat") {
                    void this.selectSidebarTab(target)
                }
            }, {signal})
        })
        if (this.currentContainer !== container) {
            this.currentContainer = container
            this.rebuildSplitView(container)
        } else {
            this.updateToggleButtons()
        }
    }

    public toggleSourcePanel(): void {
        if (!this.supportsCurrentView()) {
            return
        }
        this.togglePanel("source")
    }

    public toggleSidebarPanel(): void {
        if (!this.supportsCurrentView()) {
            return
        }
        this.togglePanel("sidebar")
    }

    public async selectSidebarTab(tab: "inspector" | "chat"): Promise<void> {
        if (!this.supportsCurrentView()) {
            return
        }
        this.isCopilotEnabled = tab === "chat"
        this.updateSidebarMode()
        this.updateToggleButtons()
        await this.context.actionDispatcher.dispatch("Synchronize", {
            editorCopilotEnabled: this.isCopilotEnabled,
        })
    }

    private supportsCurrentView(): boolean {
        return document.getElementById("split-container") !== null
    }

    private rebuildSplitView(container: HTMLElement): void {
        this.splitInstance?.destroy?.()
        this.splitInstance = null
        const source = document.getElementById("source")
        const content = document.getElementById("content")
        const sidebar = document.getElementById("sidebar")
        if (!(source instanceof HTMLElement) || !(content instanceof HTMLElement) || !(sidebar instanceof HTMLElement)) {
            return
        }
        const savedSizes = this.parseSplitSizes(container.dataset.splitSizes)
        this.applyPanelWidths(savedSizes)
        // noinspection JSUnusedGlobalSymbols
        this.splitInstance = window.Split([source, content, sidebar], {
            sizes: savedSizes,
            minSize: 0,
            gutterSize: 3,
            cursor: "col-resize",
            onDrag: () => this.updateToggleButtons(),
            onDragEnd: () => {
                this.updateToggleButtons()
                this.saveSplitState()
            },
            elementStyle: (dimension: string, size: number, gutterSize: number) => ({
                [dimension]: `calc(${size}% - ${gutterSize}px)`,
            }),
            gutterStyle: () => ({
                "background-color": "var(--border-color)",
                "width": "3px",
            }),
        }) as SplitInstance
        this.updateToggleButtons()
    }

    private parseSplitSizes(rawSizes: string | undefined): [number, number, number] {
        if (!rawSizes) {
            return [20, 60, 20]
        }
        try {
            const parsed = JSON.parse(rawSizes) as unknown
            if (Array.isArray(parsed) && parsed.length === 3) {
                return [Number(parsed[0]), Number(parsed[1]), Number(parsed[2])]
            }
        } catch {
        }
        return [20, 60, 20]
    }

    private saveSplitState(): void {
        if (!this.splitInstance) {
            return
        }
        const sizes = this.splitInstance.getSizes()
        const container = document.getElementById("split-container")
        if (container instanceof HTMLElement) {
            container.dataset.splitSizes = JSON.stringify(sizes)
        }
        void this.context.httpClient.post("Synchronize", {editorSplitSizes: sizes})
    }

    private updateSidebarMode(): void {
        const inspectorPane = document.getElementById("inspector-pane")
        const aiPane = document.getElementById("ai-copilot-pane")
        if (!(inspectorPane instanceof HTMLElement) || !(aiPane instanceof HTMLElement)) {
            return
        }
        inspectorPane.style.display = this.isCopilotEnabled ? "none" : "block"
        aiPane.style.display = this.isCopilotEnabled ? "block" : "none"
        document.querySelectorAll<HTMLButtonElement>("[data-sidebar-tab]").forEach(btn => {
            const isActive =
                (btn.dataset.sidebarTab === "chat" && this.isCopilotEnabled) ||
                (btn.dataset.sidebarTab === "inspector" && !this.isCopilotEnabled)
            btn.classList.toggle("active", isActive)
        })
    }

    private updateToggleButtons(): void {
        const sourceButton = document.getElementById("btn-toggle-source")
        const sidebarButton = document.getElementById("btn-toggle-sidebar")
        const sizes = this.splitInstance?.getSizes() ?? [20, 60, 20]
        sourceButton?.classList.toggle("active", sizes[0] > 2)
        sidebarButton?.classList.toggle("active", sizes[2] > 2)
    }

    private togglePanel(panel: SplitPanel): void {
        if (!this.splitInstance) {
            return
        }
        const sizes = this.splitInstance.getSizes()
        if (panel === "source") {
            if (sizes[0] < 2) {
                this.splitInstance.setSizes([20, 60, 20])
            } else {
                this.splitInstance.collapse(0)
            }
        }
        if (panel === "sidebar") {
            if (sizes[2] < 2) {
                this.splitInstance.setSizes([20, 60, 20])
            } else {
                this.splitInstance.collapse(2)
            }
        }
        this.updateToggleButtons()
        this.saveSplitState()
        window.setTimeout(() => {
            const graph = window.cy as { resize?: () => void } | undefined
            graph?.resize?.()
        }, 150)
    }

    private applyPanelWidths(sizes: [number, number, number]): void {
        const source = document.getElementById("source")
        const content = document.getElementById("content")
        const sidebar = document.getElementById("sidebar")
        if (!(source instanceof HTMLElement) || !(content instanceof HTMLElement) || !(sidebar instanceof HTMLElement)) {
            return
        }
        source.style.width = `${sizes[0]}%`
        content.style.width = `${sizes[1]}%`
        sidebar.style.width = `${sizes[2]}%`
    }

}
