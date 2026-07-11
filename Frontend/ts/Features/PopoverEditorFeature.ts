import {Feature} from "@/Application/Feature"
import {Popover} from "bootstrap"

export abstract class PopoverEditorFeature extends Feature {
    protected abstract readonly triggerSelector: string
    protected abstract readonly popoverClass: string
    protected abstract readonly templateId: string
    protected abstract readonly loadErrorMessage: string

    private activePopover: Popover | null = null
    private readonly popoverByElement = new WeakMap<HTMLElement, Popover>()
    private activeTrigger: HTMLElement | null = null

    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const trigger = target.closest<HTMLElement>(this.triggerSelector)
        if (trigger) {
            event.preventDefault()
            event.stopPropagation()
            event.stopImmediatePropagation()
            void this.openPopover(trigger)
            return
        }
        if (!this.activePopover || target.closest(".popover")) {
            return
        }
        this.hideActivePopover()
    }

    private readonly onDocumentEscape = (event: KeyboardEvent): void => {
        if (event.key !== "Escape" || !this.activePopover) {
            return
        }
        this.hideActivePopover()
    }

    private readonly onShownPopover = (event: Event): void => {
        const trigger = event.target
        if (!(trigger instanceof HTMLElement) || !this.popoverByElement.has(trigger)) {
            if (this.activePopover && trigger !== this.activeTrigger) {
                this.hideActivePopover()
            }
            return
        }
        const popoverBody = document.querySelector(`.${this.popoverClass} .popover-body`)
        if (!popoverBody) {
            return
        }
        this.configurePopover(popoverBody)
    }

    public override start(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("keydown", this.onDocumentEscape)
        document.addEventListener("shown.bs.popover", this.onShownPopover)
    }

    protected abstract prepareRequestURL(pageURL: URL, trigger: HTMLElement): string | null

    protected abstract configurePopover(popoverBody: Element): void

    protected popoverDidShow(): void {
    }

    protected hideActivePopover(): void {
        this.activePopover?.hide()
        this.activePopover = null
        this.activeTrigger = null
    }

    private async openPopover(trigger: HTMLElement): Promise<void> {
        const popover = this.popoverFor(trigger)
        if (this.activePopover === popover) {
            this.hideActivePopover()
            return
        }
        if (this.activePopover) {
            this.hideActivePopover()
        }

        const pageURL = new URL(window.location.href)
        const validationError = this.prepareRequestURL(pageURL, trigger)
        if (validationError) {
            this.showError(trigger, popover, validationError)
            return
        }

        trigger.classList.add("loading")

        try {
            const response = await this.context.httpClient.get(pageURL.href)
            if (!response.ok) {
                const message = await this.context.httpClient.tryGetErrorMessage(response)
                await this.context.desktopBridge.showErrorBox(message)
                return
            }
            const html = await response.text()
            const doc = new DOMParser().parseFromString(html, "text/html")
            const template = doc.getElementById(this.templateId)
            if (!template) {
                this.showError(trigger, popover, this.loadErrorMessage)
                return
            }
            popover.setContent({".popover-body": template.innerHTML})
            this.activePopover = popover
            this.activeTrigger = trigger
            popover.show()
            this.popoverDidShow()
        } catch (error) {
            console.error("Failed to load popover:", error)
            this.showError(trigger, popover, this.loadErrorMessage)
        } finally {
            trigger.classList.remove("loading")
        }
    }

    private popoverFor(trigger: HTMLElement): Popover {
        const existing = this.popoverByElement.get(trigger)
        if (existing) {
            return existing
        }
        const popover = new Popover(trigger, {
            container: "body",
            html: true,
            sanitize: false,
            trigger: "manual",
            content: "Loading...",
            customClass: this.popoverClass,
            placement: "auto",
            fallbackPlacements: ["top", "bottom", "left", "right"],
        })
        this.popoverByElement.set(trigger, popover)
        return popover
    }

    private showError(trigger: HTMLElement, popover: Popover, message: string): void {
        popover.setContent({
            ".popover-body": `<div class="p-3 text-danger"><span class="material-symbols-outlined">warning</span><span class="px-2">${message}</span></div>`,
        })
        this.activePopover = popover
        this.activeTrigger = trigger
        popover.show()
        setTimeout(() => {
            if (this.activePopover !== popover) {
                return
            }
            this.hideActivePopover()
        }, 2000)
    }
}
