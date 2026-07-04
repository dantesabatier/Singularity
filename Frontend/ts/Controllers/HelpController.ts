import {ViewController} from "@/Application/ViewController"

export class HelpController extends ViewController {
    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element) || !this.supportsCurrentView()) {
            return
        }
        const anchorElement = target.closest<HTMLElement>("[data-help-anchor]")
        if (!anchorElement) {
            return
        }
        event.preventDefault()
        const anchor = anchorElement.dataset.helpAnchor
        if (!anchor) {
            return
        }
        this.showPage(anchor)
    }
    private readonly onDocumentKeyDown = (event: KeyboardEvent): void => {
        if (!this.supportsCurrentView()) {
            return
        }
        if (event.key === "Escape" || (event.key === "w" && (event.metaKey || event.ctrlKey))) {
            event.preventDefault()
            window.close()
        }
    }
    private readonly onSearchInput = (event: Event): void => {
        const input = event.target
        if (!(input instanceof HTMLInputElement)) {
            return
        }
        this.filterIndex(input.value.trim().toLowerCase())
    }
    private readonly onPopState = (): void => {
        this.showPage(this.currentAnchor(), false)
    }

    protected override supportsCurrentView(): boolean {
        return document.querySelector('#main[data-view="help"]') !== null
    }

    protected override setup(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("keydown", this.onDocumentKeyDown)
        window.addEventListener("popstate", this.onPopState)
        document.getElementById("help-search-input")?.addEventListener("input", this.onSearchInput)
        this.showPage(this.currentAnchor(), false)
    }

    private currentAnchor(): string {
        const hash = window.location.hash.slice(1)
        if (hash) {
            return hash
        }
        return document.querySelector('#main[data-view="help"]')?.getAttribute("data-anchor") ?? "welcome"
    }

    private showPage(anchor: string, pushState = true): void {
        const pages = Array.from(document.querySelectorAll<HTMLElement>("[data-help-page]"))
        const match = pages.find((page) => page.dataset.helpPage === anchor)
        if (!match) {
            return
        }
        pages.forEach((page) => page.classList.toggle("d-none", page !== match))
        document.querySelectorAll<HTMLElement>(".help-page-link").forEach((link) => {
            link.classList.toggle("active", link.dataset.helpAnchor === anchor)
        })
        document.getElementById("help-content")?.scrollTo({top: 0})
        if (pushState && window.location.hash.slice(1) !== anchor) {
            history.pushState(null, "", `#${anchor}`)
        }
    }

    private filterIndex(query: string): void {
        const links = Array.from(document.querySelectorAll<HTMLElement>(".help-page-link"))
        let visibleCount = 0
        links.forEach((link) => {
            const title = (link.dataset.helpTitle ?? "").toLowerCase()
            const matches = query === "" || title.includes(query)
            link.classList.toggle("d-none", !matches)
            if (matches) {
                visibleCount += 1
            }
        })
        document.querySelectorAll<HTMLElement>("#help-index nav").forEach((group) => {
            const hasVisible = group.querySelector(".help-page-link:not(.d-none)") !== null
            group.classList.toggle("d-none", !hasVisible)
        })
        document.getElementById("help-no-results")?.classList.toggle("d-none", visibleCount > 0)
    }
}
