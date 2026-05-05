import {DesktopBridge} from "@/Services/DesktopBridge"
import {HttpClient} from "@/Services/HttpClient"

export class ViewNavigator {
    public constructor(private readonly httpClient: HttpClient, private readonly desktopBridge: DesktopBridge) {
    }

    public async replace(url: string, cache?: RequestCache): Promise<boolean> {
        const html = await this.load(url, cache)
        if (html === undefined) {
            return false
        }

        const scrollPositions = this.captureScrollPositions()
        const documentFragment = new DOMParser().parseFromString(html, "text/html")
        const nextMain = documentFragment.getElementById("main")
        const currentMain = document.getElementById("main")
        if (currentMain && nextMain) {
            currentMain.innerHTML = nextMain.innerHTML
        }
        this.restoreScrollPositions(scrollPositions)
        this.resetBodyState()
        this.removeModalBackdrop()
        document.dispatchEvent(new CustomEvent("view:updated", {
            detail: {url},
        }))
        return true
    }

    public async push(url: string, cache?: RequestCache): Promise<boolean> {
        const replaced = await this.replace(url, cache)
        if (!replaced) {
            return false
        }
        const current = new URL(window.location.href)
        const next = new URL(url, window.location.origin)
        if (current.href !== next.href) {
            history.pushState({url: next.href}, "", next.href)
        }
        return true
    }

    public replaceZones(html: string, url: string, zones = ["content", "inspector-pane", "ai-copilot-pane", "editor-actions-menu", "breadcrumb-list"]): void {
        const doc = new DOMParser().parseFromString(html, "text/html")
        for (const id of zones) {
            const source = doc.getElementById(id)
            const target = document.getElementById(id)
            if (source && target) {
                target.innerHTML = source.innerHTML
            }
        }
        document.dispatchEvent(new CustomEvent("view:updated", {detail: {url}}))
    }

    public async load(url: string, cache?: RequestCache): Promise<string | undefined> {
        let response: Response
        try {
            response = await this.httpClient.get(url, cache)
        } catch (error) {
            await this.desktopBridge.showErrorBox(error)
            return undefined
        }
        if (!response.ok) {
            const message = await this.httpClient.tryGetErrorMessage(response)
            await this.desktopBridge.showErrorBox(message)
            return undefined
        }
        return await response.text()
    }

    private captureScrollPositions(): Record<string, number> {
        return Array.from(document.querySelectorAll<HTMLElement>(`[class*="scroll-view"]`)).reduce<Record<string, number>>((result, element) => {
            if (element.id.length > 0) {
                result[element.id] = element.scrollTop
            }
            return result
        }, {})
    }

    private restoreScrollPositions(scrollPositions: Record<string, number>): void {
        document.querySelectorAll<HTMLElement>(`[class*="scroll-view"]`).forEach((element) => {
            const position = scrollPositions[element.id]
            if (position !== undefined) {
                element.scroll(0, position)
            }
        })
    }

    private resetBodyState(): void {
        document.body.removeAttribute("class")
        document.body.removeAttribute("style")
    }

    private removeModalBackdrop(): void {
        document.querySelector(".modal-backdrop")?.remove()
    }
}
