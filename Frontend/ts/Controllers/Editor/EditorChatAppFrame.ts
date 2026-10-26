type AppCSP = {connectDomains?: string[]; resourceDomains?: string[]; frameDomains?: string[]; baseUriDomains?: string[]}

/** An interface a tool declares, as `resources/read` answers it. */
export type ChatAppResource = {uri: string; mimeType: string; text: string; _meta?: {ui?: {csp?: AppCSP; prefersBorder?: boolean}}}

/** The stored call whose answer the interface renders. */
export type ChatAppToolCall = {name: string; input: unknown; result?: string | null; structuredContent?: Record<string, unknown> | null; isError?: boolean}

type JSONRPCMessage = {jsonrpc: "2.0"; id?: number | string; method?: string; params?: Record<string, unknown>; result?: unknown; error?: {code: number; message: string}}

// The MCP Apps protocol revision this host speaks.
const APP_PROTOCOL_VERSION = "2026-01-26"
// Until the interface reports its size there is no measure, and without a minimum the frame collapses.
const APP_FRAME_MINIMUM_HEIGHT = 120
const JSONRPC_METHOD_NOT_FOUND = -32601
const JSONRPC_INTERNAL_ERROR = -32603

class AppRequestError extends Error {
    public constructor(message: string, public readonly code: number = JSONRPC_INTERNAL_ERROR) {
        super(message)
    }
}

// The policy is built from what the interface declared in `_meta.ui.csp`: whatever it does not declare it cannot reach.
function contentSecurityPolicy(csp: AppCSP = {}): string {
    const sources = (domains: string[] | undefined, base: string[]): string => [...base, ...(domains ?? [])].join(" ") || "'none'"
    const resources = csp.resourceDomains ?? []
    return [
        "default-src 'none'",
        `script-src ${sources(resources, ["'unsafe-inline'"])}`,
        `style-src ${sources(resources, ["'unsafe-inline'"])}`,
        `img-src ${sources(resources, ["data:", "blob:"])}`,
        `font-src ${sources(resources, ["data:"])}`,
        `media-src ${sources(resources, ["data:", "blob:"])}`,
        `connect-src ${sources(csp.connectDomains, [])}`,
        `frame-src ${sources(csp.frameDomains, [])}`,
        `base-uri ${sources(csp.baseUriDomains, [])}`,
    ].join("; ")
}

// The markup may be a whole document or a fragment; either way the policy precedes every script.
function composeDocument(markup: string, csp?: AppCSP): string {
    const meta = `<meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${contentSecurityPolicy(csp)}">`
    if (/<head[^>]*>/i.test(markup)) {
        return markup.replace(/<head[^>]*>/i, head => head + meta)
    }
    return `<!doctype html><html><head>${meta}</head><body>${markup}</body></html>`
}

/**
 * Hosts one MCP App beside a chat answer. The iframe has an opaque origin, so the interface reaches nothing of the
 * editor's own: everything it needs it asks for over `postMessage`, and the host decides whether to serve it.
 */
export class EditorChatAppFrame {
    public readonly element: HTMLElement
    private readonly frame: HTMLIFrameElement
    private readonly onMessage = (event: MessageEvent): void => {
        // The chat is re-rendered on navigation, so a frame no longer in the document stops listening on its own.
        if (!this.frame.isConnected) {
            this.disconnect()
            return
        }
        const message = event.data as JSONRPCMessage | null
        if (!message || message.jsonrpc !== "2.0" || event.source !== this.frame.contentWindow) {
            return
        }
        if (message.id !== undefined && message.method) {
            void this.serve(message.id, message.method, message.params ?? {})
            return
        }
        if (message.method === "ui/notifications/initialized") {
            this.deliver()
            return
        }
        if (message.method === "ui/notifications/size-changed") {
            const height = Number(message.params?.height)
            if (Number.isFinite(height) && height > 0) {
                this.frame.style.height = `${Math.ceil(height)}px`
            }
        }
    }
    private readonly themeObserver = new MutationObserver(() => {
        this.notify("ui/notifications/host-context-changed", {theme: this.theme})
    })

    public constructor(private readonly toolCall: ChatAppToolCall, private readonly app: ChatAppResource) {
        // The frame is always the host's, since it carries the header; `prefersBorder` adds no second one.
        this.element = document.createElement("div")
        this.element.className = "ai-app"
        this.frame = document.createElement("iframe")
        this.frame.className = "ai-app__frame"
        this.frame.style.height = `${APP_FRAME_MINIMUM_HEIGHT}px`
        this.frame.setAttribute("sandbox", "allow-scripts")
        this.frame.referrerPolicy = "no-referrer"
        this.frame.title = toolCall.name
        this.frame.srcdoc = composeDocument(app.text, app._meta?.ui?.csp)
        const data = this.buildData()
        this.element.append(this.buildHeader(data), this.frame, data)
        window.addEventListener("message", this.onMessage)
        this.themeObserver.observe(document.documentElement, {attributes: true, attributeFilter: ["data-bs-theme"]})
    }

    private get theme(): "light" | "dark" {
        return document.documentElement.dataset.bsTheme === "light" ? "light" : "dark"
    }

    // The header names the tool the view came from and opens the data that draws it, without searching the call group for it.
    private buildHeader(data: HTMLElement): HTMLElement {
        const header = document.createElement("div")
        header.className = "ai-app__header"
        header.innerHTML = `<span class="material-symbols-outlined ai-app__icon">manufacturing</span><span class="ai-app__title"></span>`
        header.querySelector(".ai-app__title")!.textContent = this.toolCall.name
        const toggle = document.createElement("button")
        toggle.type = "button"
        toggle.className = "ai-app__toggle"
        toggle.title = "Show data"
        toggle.innerHTML = `<span class="material-symbols-outlined">code</span>`
        // The view is hidden rather than discarded, so it keeps whatever the user changed in it.
        toggle.addEventListener("click", () => {
            const showsData = data.hidden === true
            data.hidden = !showsData
            this.frame.hidden = showsData
            toggle.classList.toggle("ai-app__toggle--active", showsData)
            toggle.title = showsData ? "Show view" : "Show data"
        })
        header.appendChild(toggle)
        return header
    }

    private buildData(): HTMLElement {
        const data = document.createElement("div")
        data.className = "ai-app__data"
        data.hidden = true
        for (const [label, value] of [["Input", this.toolCall.input], ["Data", this.toolCall.structuredContent]] as const) {
            const section = document.createElement("div")
            const caption = document.createElement("span")
            caption.className = "ai-app__label"
            caption.textContent = label
            const pre = document.createElement("pre")
            pre.textContent = JSON.stringify(value ?? {}, null, 2)
            section.append(caption, pre)
            data.appendChild(section)
        }
        return data
    }

    private disconnect(): void {
        window.removeEventListener("message", this.onMessage)
        this.themeObserver.disconnect()
    }

    // The interface is listening: it receives the arguments and the stored answer, the one the model read. The tool is not run again to draw it.
    private deliver(): void {
        this.notify("ui/notifications/tool-input", {arguments: this.toolCall.input ?? {}})
        this.notify("ui/notifications/tool-result", {
            content: [{type: "text", text: this.toolCall.result ?? ""}],
            structuredContent: this.toolCall.structuredContent,
            isError: this.toolCall.isError === true,
        })
    }

    private async serve(id: number | string, method: string, params: Record<string, unknown>): Promise<void> {
        try {
            this.post({id, result: await this.resolve(method, params)})
        } catch (error) {
            const code = error instanceof AppRequestError ? error.code : JSONRPC_INTERNAL_ERROR
            this.post({id, error: {code, message: error instanceof Error ? error.message : "The request failed."}})
        }
    }

    private async resolve(method: string, params: Record<string, unknown>): Promise<unknown> {
        if (method === "ui/initialize") {
            return {
                protocolVersion: APP_PROTOCOL_VERSION,
                hostInfo: {name: "Singularity", version: "1.0.0"},
                hostCapabilities: {serverTools: {}, openLinks: {}},
                hostContext: {
                    theme: this.theme,
                    displayMode: "inline",
                    availableDisplayModes: ["inline"],
                    locale: navigator.language,
                    platform: "desktop",
                    containerDimensions: {width: this.element.clientWidth},
                    styles: {variables: {"--font-sans": getComputedStyle(document.body).fontFamily}},
                },
            }
        }
        if (method === "tools/call") {
            return this.callTool(String(params.name ?? ""), params.arguments ?? {})
        }
        if (method === "ui/open-link") {
            const url = new URL(String(params.url ?? ""))
            if (!["http:", "https:"].includes(url.protocol)) {
                throw new AppRequestError("Only web links are opened.")
            }
            window.open(url.href, "_blank", "noopener,noreferrer")
            return {}
        }
        if (method === "ping") {
            return {}
        }
        throw new AppRequestError(`Method not available: ${method}`, JSONRPC_METHOD_NOT_FOUND)
    }

    private async callTool(name: string, args: unknown): Promise<unknown> {
        const response = await fetch("/chat/apps/call", {
            method: "POST",
            headers: {
                "Content-Type": "application/json; charset=utf-8",
                "X-Requested-With": "XmlHttpRequest",
            },
            body: JSON.stringify({name, arguments: args}),
        })
        const data = await response.json().catch(() => null) as {error?: {localizedFailureReason?: string | null}} | null
        if (!response.ok) {
            throw new AppRequestError(data?.error?.localizedFailureReason ?? `HTTP ${response.status}`)
        }
        return data
    }

    private notify(method: string, params: Record<string, unknown>): void {
        this.post({method, params})
    }

    private post(message: Omit<JSONRPCMessage, "jsonrpc">): void {
        this.frame.contentWindow?.postMessage({jsonrpc: "2.0", ...message}, "*")
    }
}
