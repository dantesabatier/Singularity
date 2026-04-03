import {Feature} from "@/Application/Feature"
import {Tooltip} from "bootstrap"

export class TooltipFeature extends Feature {
    private readonly tooltipsByElement = new WeakMap<HTMLElement, Tooltip>()

    private readonly onDocumentMouseOver = (event: Event): void => {
        const element = (event.target as Element | null)?.closest<HTMLElement>('[data-bs-toggle="tooltip"]')
        if (!element) {
            return
        }
        this.ensureTooltip(element)
    }

    private readonly onDocumentFocusIn = (event: Event): void => {
        const element = (event.target as Element | null)?.closest<HTMLElement>('[data-bs-toggle="tooltip"]')
        if (!element) {
            return
        }
        this.ensureTooltip(element)
    }

    private readonly onDocumentClick = (event: Event): void => {
        const element = (event.target as Element | null)?.closest<HTMLElement>('[data-bs-toggle="tooltip"]')
        if (!element) {
            return
        }
        this.ensureTooltip(element).hide()
    }

    public override refresh(): void {
        document.addEventListener("mouseover", this.onDocumentMouseOver)
        document.addEventListener("focusin", this.onDocumentFocusIn)
        document.addEventListener("click", this.onDocumentClick)
    }

    private ensureTooltip(element: HTMLElement): Tooltip {
        const existing = this.tooltipsByElement.get(element)
        if (existing) {
            return existing
        }
        const tooltip = new Tooltip(element, {container: "body", trigger: "hover"})
        this.tooltipsByElement.set(element, tooltip)
        return tooltip
    }
}
