import {ApplicationContext} from "@/Application/ApplicationContext"
import { marked } from "marked"
import hljs from "highlight.js/lib/core"
import php from "highlight.js/lib/languages/php"
import typescript from "highlight.js/lib/languages/typescript"
import javascript from "highlight.js/lib/languages/javascript"
import json from "highlight.js/lib/languages/json"
import bash from "highlight.js/lib/languages/bash"
import xml from "highlight.js/lib/languages/xml"
import css from "highlight.js/lib/languages/css"
import sql from "highlight.js/lib/languages/sql"
import DOMPurify from "dompurify"

let languagesRegistered = false

function registerHighlightLanguages(): void {
    if (languagesRegistered) {
        return
    }
    languagesRegistered = true
    hljs.registerLanguage("php", php)
    hljs.registerLanguage("typescript", typescript)
    hljs.registerLanguage("javascript", javascript)
    hljs.registerLanguage("json", json)
    hljs.registerLanguage("bash", bash)
    hljs.registerLanguage("xml", xml)
    hljs.registerLanguage("css", css)
    hljs.registerLanguage("sql", sql)
    hljs.registerAliases(["ts"], {languageName: "typescript"})
    hljs.registerAliases(["js"], {languageName: "javascript"})
    hljs.registerAliases(["html"], {languageName: "xml"})
    hljs.registerAliases(["sh", "shell", "zsh"], {languageName: "bash"})
}

type ChatMessage = {
    objectID?: string | null
    role: "user" | "assistant" | "tool"
    content: string | null
    toolCalls?: Array<{ id: string; name: string; input: unknown; isError?: boolean }> | null
    toolCallId?: string | null
    images?: Array<{ name: string; mimeType: string; data: string }>
    thumbnailURLs?: string[]
}

const DEFAULT_PROVIDER = "anthropic"
const DEFAULT_MODEL = "claude-opus-4-8"

export class EditorChatController {
    private currentConversationID: string | null = null
    private currentConversationTitle: string | null = null
    private currentProjectID: string | null = null
    private selectedProvider: string = DEFAULT_PROVIDER
    private selectedModel: string = DEFAULT_MODEL
    private pendingAttachments: File[] = []

    private readonly onSendClick = (): void => {
        if (this.abortController) {
            this.abortController.abort()
        } else {
            void this.sendMessage()
        }
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
    private currentModelMenu: Element | null = null
    private currentFileInput: HTMLInputElement | null = null
    private currentAttachBtn: Element | null = null
    private currentMessages: Element | null = null
    private abortController: AbortController | null = null

    private readonly onPopState = (): void => { this.updateContextIndicator() }

    private readonly onFileInputChange = (): void => {
        if (this.currentFileInput) {
            this.onFilesSelected(this.currentFileInput)
        }
    }
    private readonly onAttachClick = (): void => { this.currentFileInput?.click() }
    private readonly onMessagesClick = (e: Event): void => {
        const btn = (e.target as Element).closest<HTMLElement>("[data-msg-action]")
        if (btn) {
            void this.handleMessageAction(btn)
            return
        }
        const toolHeader = (e.target as Element).closest<HTMLElement>("[data-action='toggle-tools']")
        if (toolHeader) {
            this.toggleToolGroup(toolHeader)
        }
    }

    private readonly onModelMenuClick = (e: Event): void => {
        const btn = (e.target as Element).closest<HTMLElement>("[data-model]")
        if (!btn?.dataset.model) {
            return
        }
        const provider = btn.dataset.provider ?? DEFAULT_PROVIDER
        const model = btn.dataset.model
        this.applyProvider(provider, true)
        this.applyModel(model, true)
    }

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
        this.updateContextIndicator()
        window.removeEventListener("popstate", this.onPopState)
        window.addEventListener("popstate", this.onPopState)
    }

    private bindElements(): void {
        const sendBtn = document.getElementById("chat-send-btn")
        const input = document.getElementById("chat-input")
        const panel = document.getElementById("ai-chat-panel")
        const projectObjectID = panel instanceof HTMLElement ? (panel.dataset.projectObjectId ?? "") : ""
        this.currentProjectID = projectObjectID || null
        const messagesContainer = document.getElementById("chat-messages")
        this.currentConversationID = messagesContainer instanceof HTMLElement ? (messagesContainer.dataset.conversationId ?? null) : null
        this.currentConversationTitle = messagesContainer instanceof HTMLElement ? (messagesContainer.dataset.conversationTitle ?? null) : null

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
        if (fileInput instanceof HTMLInputElement && fileInput !== this.currentFileInput) {
            this.currentFileInput?.removeEventListener("change", this.onFileInputChange)
            fileInput.addEventListener("change", this.onFileInputChange)
            this.currentFileInput = fileInput
        }
        const attachBtn = document.getElementById("chat-attach-btn")
        if (attachBtn && attachBtn !== this.currentAttachBtn) {
            this.currentAttachBtn?.removeEventListener("click", this.onAttachClick)
            attachBtn.addEventListener("click", this.onAttachClick)
            this.currentAttachBtn = attachBtn
        }

        const messages = document.getElementById("chat-messages")
        if (messages && messages !== this.currentMessages) {
            this.currentMessages?.removeEventListener("click", this.onMessagesClick)
            messages.addEventListener("click", this.onMessagesClick)
            this.currentMessages = messages
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
        if (panel.dataset.conversationLocked === "true") {
            this.lockModelPicker()
        }
    }

    private bindModelPicker(): void {
        const menu = document.querySelector(".ai-model-dropdown")
        if (!menu || menu === this.currentModelMenu) {
            return
        }
        this.currentModelMenu?.removeEventListener("click", this.onModelMenuClick)
        menu.addEventListener("click", this.onModelMenuClick)
        this.currentModelMenu = menu
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

        const optimisticBubble = this.appendMessageBubble({role: "user", content, images: images.length > 0 ? images : undefined, thumbnailURLs: thumbnailURLs.length > 0 ? thumbnailURLs : undefined})

        const isNewConversation = !this.currentConversationID
        const conversationTitle = (!this.currentConversationTitle || this.currentConversationTitle.startsWith("New conversation"))
            ? content
            : this.currentConversationTitle

        const body: Record<string, unknown> = {
            project: this.currentProjectID,
            conversation: {objectID: this.currentConversationID, title: conversationTitle},
            content,
            provider: this.selectedProvider,
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

        this.abortController = new AbortController()
        try {
            const response = await fetch("/chat", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json; charset=utf-8",
                    "X-Requested-With": "XmlHttpRequest",
                },
                body: JSON.stringify(body),
                signal: this.abortController.signal,
            })

            if (!response.ok) {
                this.appendErrorBubble(await this.extractErrorMessage(response))
                this.setStatus("idle")
                return
            }

            const data = await response.json() as { objectID: string; title: string; messages: ChatMessage[] }
            if (!this.currentConversationID) {
                this.currentConversationID = String(data.objectID)
            }
            this.currentConversationTitle = data.title
            const titleEl = document.querySelector(".ai-chat-title")
            if (titleEl) {
                titleEl.textContent = data.title
            }
            let modelWasChanged = false
            for (const msg of data.messages.slice(1)) {
                this.appendMessageBubble(msg)
                if (msg.toolCalls && msg.toolCalls.length > 0) {
                    modelWasChanged = true
                }
            }
            this.setStatus("idle")
            this.lockModelPicker()

            if (modelWasChanged) {
                await this.context.viewNavigator.push(window.location.href)
            } else if (isNewConversation) {
                await this.reloadSelectedConversation()
            }
        } catch (e) {
            if (e instanceof DOMException && e.name === "AbortError") {
                optimisticBubble?.remove()
                this.setStatus("idle")
                return
            }
            throw e
        } finally {
            this.abortController = null
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
            const icon = btn.querySelector("span.material-symbols-outlined")
            if (icon) {
                icon.textContent = "check"
                window.setTimeout(() => { icon.textContent = "content_paste" }, 1000)
            }
        } else if (action === "edit") {
            this.enterEditMode(msgWrapper, messageID)
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

    private buildActionBar(role: "user" | "assistant"): HTMLElement {
        const bar = document.createElement("div")
        bar.className = "ai-msg-actions"

        const copyBtn = document.createElement("button")
        copyBtn.className = "ai-msg-action-btn"
        copyBtn.dataset.msgAction = "copy"
        copyBtn.title = "Copy"
        copyBtn.innerHTML = `<span class="material-symbols-outlined">content_paste</span>`
        bar.appendChild(copyBtn)

        if (role === "user") {
            const editBtn = document.createElement("button")
            editBtn.className = "ai-msg-action-btn"
            editBtn.dataset.msgAction = "edit"
            editBtn.title = "Edit"
            editBtn.innerHTML = `<span class="material-symbols-outlined">edit</span>`
            bar.appendChild(editBtn)
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
        this.scrollToBottom()
    }

    private async reloadSelectedConversation(): Promise<void> {
        const nextUrl = new URL(window.location.href)
        nextUrl.searchParams.delete("conversation")
        await this.context.viewNavigator.navigatePartial(nextUrl)
    }

    private appendMessageBubble(message: ChatMessage): HTMLElement | undefined {
        if (message.role === "tool") {
            return undefined
        }

        const container = document.getElementById("chat-messages")
        if (!container) {
            return
        }

        const wrapper = document.createElement("div")
        wrapper.className = `ai-msg ai-msg--${message.role}`
        if (message.objectID) {
            wrapper.dataset.messageId = message.objectID
        }

        const bubble = document.createElement("div")
        bubble.className = "ai-bubble"

        if (message.role === "assistant") {
            if (message.content) {
                bubble.innerHTML = this.renderMarkdown(message.content)
                bubble.setAttribute("data-md", "")
                registerHighlightLanguages()
                bubble.querySelectorAll("pre code").forEach((block) => {
                    hljs.highlightElement(block as HTMLElement)
                })
            }
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

        if (bubble.children.length > 0 || message.content) {
            wrapper.appendChild(bubble)
        }

        if (message.toolCalls?.length) {
            wrapper.appendChild(this.buildToolGroup(message.toolCalls))
        }

        if (message.role === "user" || message.content) {
            wrapper.appendChild(this.buildActionBar(message.role as "user" | "assistant"))
        }

        container.appendChild(wrapper)
        this.scrollToBottom()
        return wrapper
    }

    private buildToolGroup(toolCalls: Array<{ id: string; name: string; input: unknown; isError?: boolean }>): HTMLElement {
        const group = document.createElement("div")
        group.className = "ai-tool-group"

        const failedCount = toolCalls.filter(c => c.isError).length
        const count = toolCalls.length
        const header = document.createElement("button")
        header.type = "button"
        header.className = "ai-tool-group__header"
        header.dataset.action = "toggle-tools"
        const headerLabel = failedCount > 0
            ? `Used ${count} ${count === 1 ? "tool" : "tools"} · ${failedCount} failed`
            : `Used ${count} ${count === 1 ? "tool" : "tools"}`
        header.classList.toggle("ai-tool-group__header--error", failedCount > 0)
        header.innerHTML = `<span class="material-symbols-outlined ai-tool-group__arrow">chevron_right</span><span class="ai-tool-group__label">${headerLabel}</span>`
        group.appendChild(header)

        const list = document.createElement("div")
        list.className = "ai-tool-list"
        list.hidden = true

        for (const call of toolCalls) {
            const item = document.createElement("div")
            item.className = call.isError ? "ai-tool-item ai-tool-item--error" : "ai-tool-item"
            const icon = call.isError ? "error" : "manufacturing"
            item.innerHTML = `<span class="material-symbols-outlined ai-tool-item__icon">${icon}</span><span class="ai-tool-item__name">${this.escapeHTML(call.name)}</span>`
            list.appendChild(item)
        }

        const done = document.createElement("div")
        done.className = failedCount > 0 ? "ai-tool-done ai-tool-done--error" : "ai-tool-done"
        const doneIcon = failedCount > 0 ? "warning" : "check_circle"
        const doneLabel = failedCount > 0 ? "Completed with errors" : "Done"
        done.innerHTML = `<span class="material-symbols-outlined ai-tool-done__icon">${doneIcon}</span><span class="ai-tool-done__label">${doneLabel}</span>`
        list.appendChild(done)

        group.appendChild(list)
        return group
    }

    private lockModelPicker(): void {
        const btn = document.getElementById("chat-model-picker-btn")
        if (btn instanceof HTMLButtonElement && !btn.disabled) {
            btn.disabled = true
            btn.title = "Model locked for this conversation"
        }
    }

    private toggleToolGroup(header: HTMLElement): void {
        const list = header.nextElementSibling as HTMLElement | null
        const arrow = header.querySelector<HTMLElement>(".ai-tool-group__arrow")
        if (!list) {
            return
        }
        list.hidden = !list.hidden
        arrow?.classList.toggle("ai-tool-group__arrow--open", !list.hidden)
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
            const icon = sendBtn.querySelector<HTMLElement>(".material-symbols-outlined")
            if (state === "thinking") {
                sendBtn.removeAttribute("disabled")
                sendBtn.classList.add("is-stop")
                if (icon) icon.textContent = "stop"
            } else {
                sendBtn.classList.remove("is-stop")
                if (icon) icon.textContent = "arrow_upward"
                this.updateSendButton()
            }
        }
    }

    private updateContextIndicator(): void {
        const bar = document.getElementById("chat-context")
        if (!bar) return
        const urlParams = new URLSearchParams(window.location.search)
        const keys: Array<{ key: string; label: string; icon: string }> = [
            { key: "entity", label: "Entity", icon: "category" },
            { key: "property", label: "Property", icon: "text_fields" },
            { key: "constraint", label: "Constraint", icon: "rule" },
            { key: "index", label: "Index", icon: "view_list" },
            { key: "element", label: "Element", icon: "code" },
            { key: "role", label: "Role", icon: "manage_accounts" },
        ]
        const chips = keys
            .filter(({ key }) => urlParams.has(key))
            .map(({ label, icon }) => `<span class="ai-ctx-chip"><span class="material-symbols-outlined ai-ctx-chip__icon">${icon}</span>${label}</span>`)
        bar.innerHTML = chips.join("")
        bar.hidden = chips.length === 0
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
            bubble.innerHTML = this.renderMarkdown(content)
            bubble.setAttribute("data-md", "")
            registerHighlightLanguages()
            bubble.querySelectorAll("pre code").forEach((block) => {
                hljs.highlightElement(block as HTMLElement)
            })
        })
        this.scrollToBottom()
    }

    private scrollToBottom(): void {
        const container = document.getElementById("chat-messages")
        if (!container) {
            return
        }
        container.scrollTop = container.scrollHeight
        window.requestAnimationFrame(() => {
            container.scrollTop = container.scrollHeight
        })
    }

    private renderMarkdown(content: string): string {
        return DOMPurify.sanitize(marked.parse(content) as string)
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
