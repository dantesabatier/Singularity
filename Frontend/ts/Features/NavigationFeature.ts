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
        await this.context.viewNavigator.navigatePartial(nextUrl, {
            zones: ["content", "inspector-pane", "editor-actions-menu", "breadcrumb-list"],
            beforeReplace: (url) => this.updateSourceListActiveState(url),
        })
    }

    private updateSourceListActiveState(nextUrl: URL): void {
        // A row's selection params are its query params minus "project"; a row is a candidate when every one of its params matches the target (it's a prefix of the selection). The active row is the most specific candidate — the one whose params fully cover the deepest sidebar level present for this selection. This keeps a fetch index highlighted when one of its elements (a deeper level with no sidebar row of its own) is selected, while never lighting up the ancestor entity at the same time.
        const rows = [...document.querySelectorAll<HTMLElement>("#source [data-href]")]
        let bestSpecificity = -1
        const specificities = rows.map((el) => {
            const elHref = el.dataset.href
            if (!elHref) {
                return -1
            }
            const elUrl = new URL(elHref, window.location.origin)
            const params = [...elUrl.searchParams.entries()].filter(([k]) => k !== "project")
            const isPrefix = params.every(([k, v]) => nextUrl.searchParams.get(k) === v)
            if (!isPrefix) {
                return -1
            }
            bestSpecificity = Math.max(bestSpecificity, params.length)
            return params.length
        })
        rows.forEach((el, i) => {
            el.classList.toggle("active", specificities[i] >= 0 && specificities[i] === bestSpecificity)
        })
    }

    public override start(): void {
        document.addEventListener("click", this.onDocumentClick)
    }
}
