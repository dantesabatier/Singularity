import {ApplicationContext} from "@/Application/ApplicationContext"

type ChatMessage = {
    role: "user" | "assistant" | "tool"
    content: string | null
    toolCalls?: Array<{ id: string; name: string; input: unknown }> | null
    toolCallId?: string | null
}

const DEFAULT_PROVIDER = "anthropic"
const DEFAULT_MODEL = "claude-opus-4-7"

export class EditorChatController {
    private currentConversationID: string | null = null
    private currentProjectID: string | null = null
    private selectedProvider: string = DEFAULT_PROVIDER
    private selectedModel: string = DEFAULT_MODEL

    private readonly onSendClick = (): void => {
        void this.sendMessage()
    }
    private readonly onInputKeydown = (event: KeyboardEvent): void => {
        if (event.key === "Enter" && (event.metaKey || event.ctrlKey)) {
            void this.sendMessage()
        }
    }
    private readonly onInputChange = (): void => {
        this.updateSendButton()
    }

    private currentSendBtn: HTMLButtonElement | null = null
    private currentInput: HTMLTextAreaElement | null = null

    public constructor(private readonly context: ApplicationContext) {
    }

    public initialize(): void {
        if (!document.getElementById("ai-chat-panel")) {
            return
        }
        this.bindElements()
        this.loadSelectionsFromDOM()
        this.bindModelPicker()
    }

    private bindElements(): void {
        const sendBtn = document.getElementById("chat-send-btn")
        const input = document.getElementById("chat-input")
        const panel = document.getElementById("ai-chat-panel")
        const projectObjectID = panel instanceof HTMLElement ? (panel.dataset.projectObjectId ?? "") : ""
        this.currentProjectID = projectObjectID || null
        const messagesContainer = document.getElementById("chat-messages")
        const fromDOM = messagesContainer instanceof HTMLElement ? (messagesContainer.dataset.conversationId ?? null) : null
        const conversationID = fromDOM ?? localStorage.getItem(`ai_conversation_${projectObjectID}`)
        if (conversationID) {
            this.currentConversationID = conversationID
            if (projectObjectID) {
                localStorage.setItem(`ai_conversation_${projectObjectID}`, conversationID)
            }
        }

        if (sendBtn instanceof HTMLButtonElement && sendBtn !== this.currentSendBtn) {
            this.currentSendBtn?.removeEventListener("click", this.onSendClick)
            sendBtn.addEventListener("click", this.onSendClick)
            this.currentSendBtn = sendBtn
        }
        if (input instanceof HTMLTextAreaElement && input !== this.currentInput) {
            this.currentInput?.removeEventListener("keydown", this.onInputKeydown)
            this.currentInput?.removeEventListener("input", this.onInputChange)
            input.addEventListener("keydown", this.onInputKeydown)
            input.addEventListener("input", this.onInputChange)
            this.currentInput = input
        }
        this.updateSendButton()
    }

    private loadSelectionsFromDOM(): void {
        const panel = document.getElementById("ai-chat-panel")
        if (!(panel instanceof HTMLElement)) {
            return
        }
        const provider = panel.dataset.aiProvider ?? DEFAULT_PROVIDER
        const model = panel.dataset.aiModel ?? DEFAULT_MODEL
        this.applyProvider(provider, false)
        this.applyModel(model, false)
    }

    private bindModelPicker(): void {
        const menu = document.querySelector(".ai-model-dropdown")
        menu?.addEventListener("click", (e) => {
            const btn = (e.target as Element).closest<HTMLElement>("[data-model]")
            if (!btn?.dataset.model) {
                return
            }
            const provider = btn.dataset.provider ?? DEFAULT_PROVIDER
            const model = btn.dataset.model
            this.applyProvider(provider, true)
            this.applyModel(model, true)
        })
    }

    private applyProvider(provider: string, persist: boolean): void {
        this.selectedProvider = provider
        if (persist) {
            void this.context.actionDispatcher.dispatch("Synchronize", {
                editorAIProvider: provider,
            })
        }
    }

    private applyModel(model: string, persist: boolean): void {
        this.selectedModel = model
        const modelBtn = document.querySelector<HTMLElement>(`.ai-model-item[data-model="${model}"]`)
        const label = modelBtn?.dataset.label ?? model
        const tier = modelBtn?.dataset.tier ?? "opus"

        const nameEl = document.getElementById("chat-model-name")
        if (nameEl) {
            nameEl.textContent = label
        }
        const indicator = document.getElementById("chat-model-indicator")
        if (indicator) {
            indicator.className = `ai-model-indicator tier-${tier}`
        }
        if (persist) {
            void this.context.actionDispatcher.dispatch("Synchronize", {
                editorAIModel: model,
            })
        }
    }

    private async sendMessage(): Promise<void> {
        const input = document.getElementById("chat-input")
        if (!(input instanceof HTMLTextAreaElement)) {
            return
        }
        const content = input.value.trim()
        if (!content) {
            return
        }
        input.value = ""
        this.setStatus("thinking")
        this.appendMessageBubble({role: "user", content})

        const response = await this.context.httpClient.post("/message", {
            project: this.currentProjectID,
            conversation: this.currentConversationID,
            content,
            model: this.selectedModel,
        })

        if (!response.ok) {
            this.setStatus("error")
            return
        }

        const data = await response.json() as {conversationID: string; messages: ChatMessage[]}
        if (!this.currentConversationID) {
            this.currentConversationID = data.conversationID
            if (this.currentProjectID) {
                localStorage.setItem(`ai_conversation_${this.currentProjectID}`, data.conversationID)
            }
        }
        let modelWasChanged = false
        for (const msg of data.messages.slice(1)) {
            this.appendMessageBubble(msg)
            if (msg.toolCalls && msg.toolCalls.length > 0) {
                modelWasChanged = true
            }
        }
        this.setStatus("idle")

        if (modelWasChanged) {
            await this.context.viewNavigator.push(window.location.href)
        }
    }

    private appendMessageBubble(message: ChatMessage): void {
        const container = document.getElementById("chat-messages")
        if (!container) {
            return
        }
        const wrapper = document.createElement("div")
        wrapper.className = `ai-msg ai-msg--${message.role}`

        if (message.role === "tool") {
            const name = message.toolCallId ?? "tool"
            wrapper.innerHTML = `<span class="ai-tool-badge"><i class="bi bi-check2-circle"></i> ${this.escapeHTML(name)}</span>`
        } else {
            const bubble = document.createElement("div")
            bubble.className = "ai-bubble"
            bubble.textContent = message.content ?? ""
            wrapper.appendChild(bubble)

            if (message.role === "assistant" && message.toolCalls?.length) {
                for (const call of message.toolCalls) {
                    const badge = document.createElement("div")
                    badge.className = "ai-tool-badge"
                    badge.innerHTML = `<i class="bi bi-gear"></i> ${this.escapeHTML(call.name)}`
                    wrapper.appendChild(badge)
                }
            }
        }

        container.appendChild(wrapper)
        container.scrollTop = container.scrollHeight
    }

    private setStatus(state: "idle" | "thinking" | "error"): void {
        const statusEl = document.getElementById("ai-status")
        if (statusEl) {
            statusEl.classList.remove("is-idle", "is-thinking", "is-error")
            statusEl.classList.add(`is-${state}`)
            statusEl.textContent = ({"idle": "Idle", "thinking": "Thinking…", "error": "Error"})[state]
        }
        const thinkingEl = document.getElementById("chat-thinking")
        if (thinkingEl) {
            thinkingEl.classList.toggle("is-visible", state === "thinking")
            if (state === "thinking") {
                const container = document.getElementById("chat-messages")
                if (container) {
                    container.scrollTop = container.scrollHeight
                }
            }
        }
        const sendBtn = document.getElementById("chat-send-btn")
        if (sendBtn instanceof HTMLButtonElement) {
            sendBtn.toggleAttribute("disabled", state === "thinking")
        }
    }

    private updateSendButton(): void {
        const sendBtn = document.getElementById("chat-send-btn")
        const input = document.getElementById("chat-input")
        if (sendBtn instanceof HTMLButtonElement) {
            const hasContent = input instanceof HTMLTextAreaElement && input.value.trim().length > 0
            sendBtn.toggleAttribute("disabled", !hasContent)
        }
    }

    private escapeHTML(value: unknown): string {
        return String(value ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "\"": "&quot;",
            "'": "&#39;"
        })[c] ?? c)
    }
}
