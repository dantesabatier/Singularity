import {Feature} from "@/Application/Feature"

export class NavigationFeature extends Feature {
    private readonly onDocumentClick = (event: MouseEvent): void => {
        if (event.defaultPrevented) {
            return
        }
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        if (target.closest('[data-bs-toggle="popover"], button, input, select, textarea, a, [contenteditable="true"]')) {
            return
        }
        const element = target.closest<HTMLElement>("[data-href]")
        if (!element) {
            return
        }
        const href = element.dataset.href
        if (!href) {
            return
        }
        event.preventDefault()
        const nextUrl = new URL(href, window.location.href)
        if (this.isSelectionNavigation(nextUrl)) {
            void this.navigatePartial(nextUrl)
        } else {
            void this.context.viewNavigator.push(nextUrl.href)
        }
    }

    private isSelectionNavigation(next: URL): boolean {
        const current = new URL(window.location.href)
        return (
            current.pathname === "/Editor" &&
            next.pathname === "/Editor" &&
            current.searchParams.get("project") === next.searchParams.get("project")
        )
    }

    private async navigatePartial(nextUrl: URL): Promise<void> {
        const partialUrl = new URL(nextUrl.href)
        partialUrl.searchParams.set("partial", "1")
        const html = await this.context.viewNavigator.load(partialUrl.href)
        if (html === undefined) {
            return
        }
        this.updateSourceListActiveState(nextUrl)
        this.context.viewNavigator.replaceZones(html, nextUrl.href)
        const current = new URL(window.location.href)
        if (current.href !== nextUrl.href) {
            history.pushState({url: nextUrl.href}, "", nextUrl.href)
        }
    }

    private updateSourceListActiveState(nextUrl: URL): void {
        document.querySelectorAll<HTMLElement>("#source [data-href]").forEach((el) => {
            const elHref = el.dataset.href
            if (!elHref) {
                return
            }
            const elUrl = new URL(elHref, window.location.origin)
            const isActive = [...elUrl.searchParams.entries()]
                .filter(([k]) => k !== "project")
                .every(([k, v]) => nextUrl.searchParams.get(k) === v)
            el.classList.toggle("active", isActive)
        })
    }

    public override start(): void {
        document.addEventListener("click", this.onDocumentClick)
    }
}
