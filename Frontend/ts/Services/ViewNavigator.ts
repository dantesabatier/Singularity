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
            const currentActiveHrefs = Array.from(currentMain.querySelectorAll(".nav-item.active, .nav-link.active"))
                .map((el) => el.getAttribute("href"))
                .filter((href): href is string => href !== null)

            if (currentActiveHrefs.length > 0) {
                nextMain.querySelectorAll(".nav-item, .nav-link").forEach((el) => {
                    const href = el.getAttribute("href")
                    if (href) {
                        el.classList.toggle("active", currentActiveHrefs.includes(href))
                    }
                })
            }
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

    public async navigatePartial(nextUrl: URL, options: {zones?: string[]; beforeReplace?: (nextUrl: URL) => void} = {}): Promise<boolean> {
        const partialUrl = new URL(nextUrl.href)
        partialUrl.searchParams.set("partial", "1")
        const html = await this.load(partialUrl.href)
        if (html === undefined) {
            return false
        }
        options.beforeReplace?.(nextUrl)
        this.replaceZones(html, nextUrl.href, options.zones)
        const current = new URL(window.location.href)
        if (current.href !== nextUrl.href) {
            history.pushState({url: nextUrl.href}, "", nextUrl.href)
        }
        return true
    }

    public replaceZones(html: string, url: string, zones = ["content", "inspector-pane", "ai-copilot-pane", "editor-actions-menu", "breadcrumb-list"]): void {
        const doc = new DOMParser().parseFromString(html, "text/html")
        const scrollPositions = this.captureScrollPositions()
        for (const id of zones) {
            const source = doc.getElementById(id)
            const target = document.getElementById(id)
            if (source && target) {
                target.innerHTML = source.innerHTML
            }
        }
        this.restoreScrollPositions(scrollPositions)
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

    private captureScrollPositions(): Record<string, { top: number; left: number }> {
        const positions: Record<string, { top: number; left: number }> = {
            window: { top: window.scrollY, left: window.scrollX },
        }
        document.querySelectorAll<HTMLElement>(`[class*="scroll-view"], [class*="overflow-auto"], [class*="overflow-y-auto"]`).forEach((element) => {
            if (element.id.length > 0) {
                positions[element.id] = {
                    top: element.scrollTop,
                    left: element.scrollLeft,
                }
            }
        })
        return positions
    }

    private restoreScrollPositions(scrollPositions: Record<string, { top: number; left: number }>): void {
        document.querySelectorAll<HTMLElement>(`[class*="scroll-view"], [class*="overflow-auto"], [class*="overflow-y-auto"]`).forEach((element) => {
            const position = scrollPositions[element.id]
            if (position !== undefined) {
                element.scrollTop = position.top
                element.scrollLeft = position.left
            }
        })
        if (scrollPositions.window !== undefined) {
            window.scrollTo(scrollPositions.window.left, scrollPositions.window.top)
        }
    }

    private resetBodyState(): void {
        document.body.removeAttribute("class")
        document.body.removeAttribute("style")
    }

    private removeModalBackdrop(): void {
        document.querySelector(".modal-backdrop")?.remove()
    }
}
