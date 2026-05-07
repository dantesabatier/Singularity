import {ApplicationContext} from "@/Application/ApplicationContext"
import { marked } from "marked"
import hljs from "highlight.js"

type ChatMessage = {
    objectID?: string | null
    role: "user" | "assistant" | "tool"
    content: string | null
    toolCalls?: Array<{ id: string; name: string; input: unknown }> | null
    toolCallId?: string | null
    images?: Array<{ name: string; mimeType: string; data: string }>
    thumbnailURLs?: string[]
}

const DEFAULT_PROVIDER = "anthropic"
const DEFAULT_MODEL = "claude-opus-4-7"

export class EditorChatController {
    private currentConversationID: string | null = null
    private currentProjectID: string | null = null
    private selectedProvider: string = DEFAULT_PROVIDER
    private selectedModel: string = DEFAULT_MODEL
    private pendingAttachments: File[] = []

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
        this.renderServerMessages()
    }

    private bindElements(): void {
        const sendBtn = document.getElementById("chat-send-btn")
        const input = document.getElementById("chat-input")
        const panel = document.getElementById("ai-chat-panel")
        const projectObjectID = panel instanceof HTMLElement ? (panel.dataset.projectObjectId ?? "") : ""
        this.currentProjectID = projectObjectID || null
        const messagesContainer = document.getElementById("chat-messages")
        this.currentConversationID = messagesContainer instanceof HTMLElement ? (messagesContainer.dataset.conversationId ?? null) : null

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

        const fileInput = document.getElementById("chat-file-input")
        if (fileInput instanceof HTMLInputElement) {
            fileInput.addEventListener("change", () => this.onFilesSelected(fileInput))
        }
        const attachBtn = document.getElementById("chat-attach-btn")
        attachBtn?.addEventListener("click", () => fileInput?.click())

        const messages = document.getElementById("chat-messages")
        messages?.addEventListener("click", (e) => {
            const btn = (e.target as Element).closest<HTMLElement>("[data-msg-action]")
            if (btn) {
                void this.handleMessageAction(btn)
            }
        })

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

        const thumbnailURLs = this.pendingAttachments.map(f => URL.createObjectURL(f))
        const images = await Promise.all(this.pendingAttachments.map(f => this.fileToBase64(f)))
        this.pendingAttachments = []
        this.clearAttachmentChips()

        this.appendMessageBubble({role: "user", content, images: images.length > 0 ? images : undefined, thumbnailURLs: thumbnailURLs.length > 0 ? thumbnailURLs : undefined})

        const isNewConversation = !this.currentConversationID

        const body: Record<string, unknown> = {
            project: this.currentProjectID,
            conversation: this.currentConversationID,
            content,
            model: this.selectedModel,
        }
        const selectionKeys = ["entity", "property", "index", "element", "constraint", "fetchRequest", "configuration", "composite", "accessControl", "role"]
        const urlParams = new URLSearchParams(window.location.search)
        for (const key of selectionKeys) {
            const val = urlParams.get(key)
            if (val) {
                body[key] = val
            }
        }
        if (images.length > 0) {
            body.images = images
        }

        const response = await this.context.httpClient.post("/message", body)

        if (!response.ok) {
            this.appendErrorBubble(await this.extractErrorMessage(response))
            this.setStatus("idle")
            return
        }

        const data = await response.json() as { conversationID: string; messages: ChatMessage[] }
        if (!this.currentConversationID) {
            this.currentConversationID = data.conversationID
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
        } else if (isNewConversation) {
            await this.navigateToConversation(data.conversationID)
        }
    }

    private async handleMessageAction(btn: HTMLElement): Promise<void> {
        const action = btn.dataset.msgAction
        const msgWrapper = btn.closest<HTMLElement>(".ai-msg")
        if (!msgWrapper) {
            return
        }
        const messageID = msgWrapper.dataset.messageId ?? null

        if (action === "copy") {
            const bubble = msgWrapper.querySelector(".ai-bubble")
            await navigator.clipboard.writeText(bubble?.textContent ?? "")
            const icon = btn.querySelector("i")
            if (icon) {
                icon.className = "bi bi-check"
                window.setTimeout(() => { icon.className = "bi bi-clipboard" }, 1000)
            }
        } else if (action === "edit") {
            this.enterEditMode(msgWrapper, messageID)
        } else if (action === "resend") {
            await this.regenerateFrom(msgWrapper, messageID, null)
        }
    }

    private enterEditMode(wrapper: HTMLElement, messageID: string | null): void {
        const bubble = wrapper.querySelector<HTMLElement>(".ai-bubble")
        if (!bubble) {
            return
        }
        const originalHTML = bubble.innerHTML
        const originalText = bubble.textContent ?? ""

        const editContainer = document.createElement("div")
        editContainer.className = "ai-bubble-edit"

        const textarea = document.createElement("textarea")
        textarea.value = originalText
        textarea.rows = 1

        const autoGrow = (): void => {
            textarea.style.height = "auto"
            textarea.style.height = `${textarea.scrollHeight}px`
        }

        const cancel = (): void => {
            bubble.innerHTML = originalHTML
            editContainer.replaceWith(bubble)
        }

        const actions = document.createElement("div")
        actions.className = "ai-edit-actions"

        const hint = document.createElement("span")
        hint.className = "ai-edit-hint"
        hint.textContent = "Esc to cancel · ⌘↵ to save"

        const cancelBtn = document.createElement("button")
        cancelBtn.type = "button"
        cancelBtn.className = "ai-edit-cancel-btn"
        cancelBtn.textContent = "Cancel"
        cancelBtn.addEventListener("click", cancel)

        const saveBtn = document.createElement("button")
        saveBtn.type = "button"
        saveBtn.className = "ai-edit-save-btn"
        saveBtn.textContent = "Save"

        const save = async (): Promise<void> => {
            if (!messageID) {
                return
            }
            const newContent = textarea.value.trim()
            if (!newContent) {
                return
            }
            saveBtn.disabled = true
            const response = await this.context.httpClient.patch("/Message", { objectID: messageID, content: newContent })
            if (!response.ok) {
                saveBtn.disabled = false
                this.appendErrorBubble(await this.extractErrorMessage(response))
                return
            }
            bubble.textContent = newContent
            editContainer.replaceWith(bubble)
        }

        saveBtn.addEventListener("click", () => { void save() })

        textarea.addEventListener("input", autoGrow)
        textarea.addEventListener("keydown", (e: KeyboardEvent) => {
            if (e.key === "Escape") {
                e.preventDefault()
                cancel()
            } else if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
                e.preventDefault()
                void save()
            }
        })

        actions.appendChild(hint)
        actions.appendChild(cancelBtn)
        actions.appendChild(saveBtn)
        editContainer.appendChild(textarea)
        editContainer.appendChild(actions)

        bubble.replaceWith(editContainer)
        textarea.focus()
        textarea.selectionStart = textarea.value.length
        autoGrow()
    }

    private async regenerateFrom(wrapper: HTMLElement, messageID: string | null, content: string | null): Promise<void> {
        if (!messageID) {
            return
        }
        this.setStatus("thinking")

        const container = document.getElementById("chat-messages")
        if (container) {
            const allMessages = Array.from(container.querySelectorAll<HTMLElement>(".ai-msg"))
            const idx = allMessages.indexOf(wrapper)
            if (idx !== -1) {
                for (const el of allMessages.slice(idx)) {
                    el.remove()
                }
            }
        }

        if (content !== null) {
            const newWrapper = document.createElement("div")
            newWrapper.className = "ai-msg ai-msg--user"
            newWrapper.dataset.messageId = messageID
            const bubble = document.createElement("div")
            bubble.className = "ai-bubble"
            bubble.textContent = content
            newWrapper.appendChild(bubble)
            newWrapper.appendChild(this.buildActionBar("user"))
            container?.appendChild(newWrapper)
        }

        const body: Record<string, unknown> = { messageObjectID: messageID }
        if (content !== null) {
            body.content = content
        }

        const response = await this.context.httpClient.post("/message/regenerate", body)

        if (!response.ok) {
            this.appendErrorBubble(await this.extractErrorMessage(response))
            this.setStatus("idle")
            return
        }

        const data = await response.json() as { conversationID: string; messages: ChatMessage[] }
        for (const msg of content !== null ? data.messages.slice(1) : data.messages) {
            this.appendMessageBubble(msg)
        }
        this.setStatus("idle")
    }

    private buildActionBar(role: "user" | "assistant"): HTMLElement {
        const bar = document.createElement("div")
        bar.className = "ai-msg-actions"

        const copyBtn = document.createElement("button")
        copyBtn.className = "ai-msg-action-btn"
        copyBtn.dataset.msgAction = "copy"
        copyBtn.title = "Copy"
        copyBtn.innerHTML = `<i class="bi bi-clipboard"></i>`
        bar.appendChild(copyBtn)

        if (role === "user") {
            const editBtn = document.createElement("button")
            editBtn.className = "ai-msg-action-btn"
            editBtn.dataset.msgAction = "edit"
            editBtn.title = "Edit"
            editBtn.innerHTML = `<i class="bi bi-pencil"></i>`
            bar.appendChild(editBtn)

            const resendBtn = document.createElement("button")
            resendBtn.className = "ai-msg-action-btn"
            resendBtn.dataset.msgAction = "resend"
            resendBtn.title = "Resend"
            resendBtn.innerHTML = `<i class="bi bi-arrow-clockwise"></i>`
            bar.appendChild(resendBtn)
        }

        return bar
    }

    private onFilesSelected(input: HTMLInputElement): void {
        const files = Array.from(input.files ?? [])
        for (const file of files) {
            if (!this.pendingAttachments.some(f => f.name === file.name && f.size === file.size)) {
                this.pendingAttachments.push(file)
            }
        }
        input.value = ""
        this.renderAttachmentChips()
        this.updateSendButton()
    }

    private renderAttachmentChips(): void {
        const container = document.getElementById("chat-attachments")
        if (!container) {
            return
        }
        container.innerHTML = ""
        for (const [index, file] of this.pendingAttachments.entries()) {
            const chip = document.createElement("div")
            chip.className = "ai-attachment-chip"

            const thumb = document.createElement("img")
            thumb.src = URL.createObjectURL(file)
            thumb.alt = file.name
            chip.appendChild(thumb)

            const name = document.createElement("span")
            name.className = "ai-attachment-name"
            name.textContent = file.name
            chip.appendChild(name)

            const removeBtn = document.createElement("button")
            removeBtn.type = "button"
            removeBtn.textContent = "×"
            removeBtn.addEventListener("click", () => {
                this.pendingAttachments.splice(index, 1)
                this.renderAttachmentChips()
                this.updateSendButton()
            })
            chip.appendChild(removeBtn)

            container.appendChild(chip)
        }
    }

    private clearAttachmentChips(): void {
        const container = document.getElementById("chat-attachments")
        if (container) {
            container.innerHTML = ""
        }
    }

    private fileToBase64(file: File): Promise<{ name: string; mimeType: string; data: string }> {
        return new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.onload = () => {
                const dataUrl = reader.result as string
                resolve({ name: file.name, mimeType: file.type, data: dataUrl.split(",")[1] ?? "" })
            }
            reader.onerror = reject
            reader.readAsDataURL(file)
        })
    }

    private async extractErrorMessage(response: Response): Promise<string> {
        let errorMessage = "Something went wrong. Please try again."
        try {
            const errorData = await response.json() as {
                error?: {
                    localizedDescription?: string | null
                    localizedFailureReason?: string | null
                    localizedRecoverySuggestion?: string | null
                }
            }
            const e = errorData.error
            if (e) {
                const title = e.localizedDescription ?? errorMessage
                let detail = e.localizedFailureReason ?? e.localizedRecoverySuggestion ?? ""
                if (detail && e.localizedRecoverySuggestion && detail !== e.localizedRecoverySuggestion) {
                    if (!detail.endsWith(".")) {
                        detail += "."
                    }
                    detail += "\n" + e.localizedRecoverySuggestion
                }
                if (detail && !detail.endsWith(".")) {
                    detail += "."
                }
                errorMessage = detail ? `${title}\n${detail}` : title
            }
        } catch {
            // ignore parse error
        }
        return errorMessage
    }

    private appendErrorBubble(message: string): void {
        const container = document.getElementById("chat-messages")
        if (!container) {
            return
        }
        const wrapper = document.createElement("div")
        wrapper.className = "ai-msg ai-msg--error"
        const bubble = document.createElement("div")
        bubble.className = "ai-bubble"
        bubble.textContent = message
        wrapper.appendChild(bubble)
        container.appendChild(wrapper)
        container.scrollTop = container.scrollHeight
    }

    private async navigateToConversation(conversationID: string): Promise<void> {
        const nextUrl = new URL(window.location.href)
        nextUrl.searchParams.set("conversation", conversationID)
        const partialUrl = new URL(nextUrl.href)
        partialUrl.searchParams.set("partial", "1")
        const html = await this.context.viewNavigator.load(partialUrl.href)
        if (html === undefined) {
            history.pushState({url: nextUrl.href}, "", nextUrl.href)
            return
        }
        this.context.viewNavigator.replaceZones(html, nextUrl.href)
        history.pushState({url: nextUrl.href}, "", nextUrl.href)
    }

    private appendMessageBubble(message: ChatMessage): void {
        const container = document.getElementById("chat-messages")
        if (!container) {
            return
        }
        const wrapper = document.createElement("div")
        wrapper.className = `ai-msg ai-msg--${message.role}`
        if (message.objectID) {
            wrapper.dataset.messageId = message.objectID
        }

        if (message.role === "tool") {
            const name = message.toolCallId ?? "tool"
            wrapper.innerHTML = `<span class="ai-tool-badge"><i class="bi bi-check2-circle"></i> ${this.escapeHTML(name)}</span>`
        } else {
            const bubble = document.createElement("div")
            bubble.className = "ai-bubble"
            if (message.role === "assistant") {
                bubble.innerHTML = marked.parse(message.content ?? "") as string
                bubble.setAttribute("data-md", "")
                bubble.querySelectorAll("pre code").forEach((block) => {
                    hljs.highlightElement(block as HTMLElement)
                })
            } else {
                if (message.thumbnailURLs?.length) {
                    const imgRow = document.createElement("div")
                    imgRow.className = "ai-msg-images"
                    for (const url of message.thumbnailURLs) {
                        const thumb = document.createElement("img")
                        thumb.src = url
                        thumb.className = "ai-msg-img"
                        imgRow.appendChild(thumb)
                    }
                    bubble.appendChild(imgRow)
                }
                bubble.appendChild(document.createTextNode(message.content ?? ""))
            }
            wrapper.appendChild(bubble)

            if (message.role === "assistant" && message.toolCalls?.length) {
                for (const call of message.toolCalls) {
                    const badge = document.createElement("div")
                    badge.className = "ai-tool-badge"
                    badge.innerHTML = `<i class="bi bi-gear"></i> ${this.escapeHTML(call.name)}`
                    wrapper.appendChild(badge)
                }
            }

            wrapper.appendChild(this.buildActionBar(message.role as "user" | "assistant"))
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
            const hasContent = (input instanceof HTMLTextAreaElement && input.value.trim().length > 0)
                || this.pendingAttachments.length > 0
            sendBtn.toggleAttribute("disabled", !hasContent)
        }
    }

    private renderServerMessages(): void {
        const container = document.getElementById("chat-messages")
        if (!container) {
            return
        }
        container.querySelectorAll<HTMLElement>(".ai-msg--assistant .ai-bubble:not([data-md])").forEach((bubble) => {
            const content = bubble.textContent?.trim() ?? ""
            bubble.innerHTML = marked.parse(content) as string
            bubble.setAttribute("data-md", "")
            bubble.querySelectorAll("pre code").forEach((block) => {
                hljs.highlightElement(block as HTMLElement)
            })
        })
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
