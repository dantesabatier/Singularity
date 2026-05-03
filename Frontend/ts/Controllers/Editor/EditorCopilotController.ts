import {ApplicationContext} from "@/Application/ApplicationContext"

type PatchWarning = {
    title?: string
    content?: string
    message?: string
}

type PatchPreviewItem = {
    title?: string
    content?: string
}

type PatchPreview = {
    summary?: string
    warnings?: PatchWarning[]
    items?: PatchPreviewItem[]
}

type CopilotState = "idle" | "thinking" | "ready" | "applying" | "done" | "error"

export class EditorCopilotController {
    private pendingProposal: PatchPreview | null = null
    private readonly onProposeClick = (): void => {
        void this.propose()
    }
    private readonly onApplyClick = (): void => {
        void this.apply()
    }
    private currentProposeButton: HTMLButtonElement | null = null
    private currentApplyButton: HTMLButtonElement | null = null

    public constructor(private readonly context: ApplicationContext) {
    }

    public initialize(): void {
        if (!this.supportsCurrentView()) {
            return
        }
        const proposeButton = document.getElementById("ai-propose-btn")
        const applyButton = document.getElementById("ai-apply-btn")
        if (!(proposeButton instanceof HTMLButtonElement) || !(applyButton instanceof HTMLButtonElement)) {
            return
        }
        if (this.currentProposeButton !== proposeButton) {
            this.currentProposeButton?.removeEventListener("click", this.onProposeClick)
            proposeButton.addEventListener("click", this.onProposeClick)
            this.currentProposeButton = proposeButton
        }
        if (this.currentApplyButton !== applyButton) {
            this.currentApplyButton?.removeEventListener("click", this.onApplyClick)
            applyButton.addEventListener("click", this.onApplyClick)
            this.currentApplyButton = applyButton
        }
        this.setState("idle")
    }

    private supportsCurrentView(): boolean {
        return document.getElementById("ai-copilot-pane") !== null
    }

    private get projectObjectID(): string {
        const runtime = document.getElementById("editor-runtime")
        if (!(runtime instanceof HTMLElement)) {
            return ""
        }
        return runtime.dataset.projectObjectId ?? ""
    }

    private get promptElement(): HTMLTextAreaElement | null {
        const element = document.getElementById("ai-prompt")
        return element instanceof HTMLTextAreaElement ? element : null
    }

    private get summaryElement(): HTMLElement | null {
        const element = document.getElementById("ai-summary")
        return element instanceof HTMLElement ? element : null
    }

    private get warningsElement(): HTMLElement | null {
        const element = document.getElementById("ai-warnings")
        return element instanceof HTMLElement ? element : null
    }

    private get operationsElement(): HTMLElement | null {
        const element = document.getElementById("ai-operations")
        return element instanceof HTMLElement ? element : null
    }

    private get statusElement(): HTMLElement | null {
        const element = document.getElementById("ai-status")
        return element instanceof HTMLElement ? element : null
    }

    private setState(state: CopilotState, label?: string): void {
        const statusElement = this.statusElement
        if (!statusElement) {
            return
        }
        statusElement.classList.remove("is-idle", "is-thinking", "is-ready", "is-applying", "is-done", "is-error")
        statusElement.classList.add(`is-${state}`)
        statusElement.textContent = label ?? ({
            idle: "Idle",
            thinking: "Thinking",
            ready: "Ready",
            applying: "Applying",
            done: "Done",
            error: "Error",
        }[state] ?? "Idle")
        const isBusy = state === "thinking" || state === "applying"
        this.currentProposeButton?.toggleAttribute("disabled", isBusy)
        this.currentApplyButton?.toggleAttribute("disabled", isBusy || !this.pendingProposal)
    }

    private async propose(): Promise<void> {
        const prompt = this.promptElement?.value.trim() ?? ""
        if (!prompt || !this.projectObjectID) {
            return
        }
        this.pendingProposal = null
        this.setState("thinking")
        this.renderThinkingState()
        const data = {
            summary: undefined,
            warnings: undefined,
            items: undefined
        }
        if (!data) {
            this.renderError("Invalid response from propose endpoint.")
            return
        }
        this.pendingProposal = data
        this.summaryElement && (this.summaryElement.textContent = data.summary ?? "Ready to apply.")
        this.renderWarnings(data.warnings ?? [])
        this.renderItems(data.items ?? [])
        this.setState("ready")
    }

    private async apply(): Promise<void> {
        if (!this.pendingProposal || !this.projectObjectID) {
            return
        }
        this.setState("applying")
        this.pendingProposal = null
        this.setState("done")
        await this.context.viewNavigator.push(window.location.href)
    }

    private renderThinkingState(): void {
        if (this.summaryElement) {
            this.summaryElement.textContent = "Thinking…"
        }
        if (this.warningsElement) {
            this.warningsElement.innerHTML = "<li>—</li>"
        }
        if (this.operationsElement) {
            this.operationsElement.innerHTML = "<li>—</li>"
        }
    }

    private renderError(message: string): void {
        if (this.summaryElement) {
            this.summaryElement.textContent = message
        }
        if (this.warningsElement) {
            this.warningsElement.innerHTML = "<li>No warnings</li>"
        }
        if (this.operationsElement) {
            this.operationsElement.innerHTML = "<li>No operations</li>"
        }
        this.setState("error")
    }

    private renderWarnings(warnings: readonly PatchWarning[]): void {
        if (!this.warningsElement) {
            return
        }
        if (warnings.length === 0) {
            this.warningsElement.innerHTML = "<li>No warnings</li>"
            return
        }
        this.warningsElement.innerHTML = warnings.map((warning) => {
            const title = warning.title ?? "Warning"
            const content = warning.content ?? warning.message ?? ""
            return `<li><strong>${this.escapeHTML(title)}:</strong> ${this.escapeHTML(content)}</li>`
        }).join("")
    }

    private renderItems(items: readonly PatchPreviewItem[]): void {
        if (!this.operationsElement) {
            return
        }
        if (items.length === 0) {
            this.operationsElement.innerHTML = "<li>No operations</li>"
            return
        }
        this.operationsElement.innerHTML = items.map((item) => {
            const title = item.title ?? "Operation"
            const content = item.content ?? ""
            return `<li><strong>${this.escapeHTML(title)}:</strong> ${this.escapeHTML(content)}</li>`
        }).join("")
    }

    private escapeHTML(value: unknown): string {
        return String(value ?? "").replace(/[&<>"']/g, (character) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "\"": "&quot;",
            "'": "&#39;",
        }[character] ?? character))
    }
}
