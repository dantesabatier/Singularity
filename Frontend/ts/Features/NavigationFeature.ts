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
        void this.context.viewNavigator.push(new URL(href, window.location.href).href)
    }

    public override start(): void {
        document.addEventListener("click", this.onDocumentClick)
    }
}
