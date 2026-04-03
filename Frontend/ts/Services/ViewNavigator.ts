import {DesktopBridge} from "@/Services/DesktopBridge"
import {HttpClient} from "@/Services/HttpClient"
import {ViewCache} from "@/Services/ViewCache"

export class ViewNavigator {
    public constructor(
        private readonly httpClient: HttpClient,
        private readonly viewCache: ViewCache,
        private readonly desktopBridge: DesktopBridge
    ) {
    }

    public async replace(url: string): Promise<boolean> {
        const html = await this.load(url)
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

    public async push(url: string): Promise<boolean> {
        const replaced = await this.replace(url)
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

    private async load(url: string): Promise<string | undefined> {
        const cached = this.viewCache.get(url)
        if (cached !== undefined) {
            return cached
        }
        let response: Response
        try {
            response = await this.httpClient.get(url)
        } catch (error) {
            await this.desktopBridge.showErrorBox(error)
            return undefined
        }
        if (!response.ok) {
            const message = await this.httpClient.tryGetErrorMessage(response)
            await this.desktopBridge.showErrorBox(message)
            return undefined
        }
        const html = await response.text()
        this.viewCache.set(url, html)
        return html
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
