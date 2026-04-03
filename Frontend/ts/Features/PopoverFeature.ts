import {Feature} from "@/Application/Feature"

export class PopoverFeature extends Feature {
    public override refresh(): void {
        document.addEventListener("click", this.handleClick)
        document.addEventListener("keydown", this.handleEscape)
    }

    private handleClick = (event: Event): void => {
        const target = (event.target as Element | null)
        if (!target) {
            return
        }
        const openTrigger = target.closest<HTMLElement>("[data-popover]")
        if (openTrigger) {
            event.stopPropagation()
            this.togglePopover(openTrigger)
            return
        }
        const closeTrigger = target.closest<HTMLElement>("[data-popover-close]")
        if (closeTrigger) {
            event.stopPropagation()
            this.closePopoverByTrigger(closeTrigger)
            return
        }
        const copyElement = target.closest<HTMLElement>(".sp-chip, .sp-example")
        if (copyElement) {
            event.stopPropagation()
            navigator.clipboard?.writeText(copyElement.dataset.copy ?? copyElement.textContent?.trim() ?? "")
            copyElement.classList.add("copied")
            setTimeout(() => copyElement.classList.remove("copied"), 900)
            return
        }
        this.closeAllPopovers()
    }

    private handleEscape = (event: KeyboardEvent): void => {
        if (event.key === "Escape") {
            this.closeAllPopovers()
        }
    }

    private togglePopover(trigger: HTMLElement): void {
        const popoverID = trigger.dataset.popover
        if (!popoverID) {
            return
        }
        const popover = document.getElementById(popoverID)
        if (!popover) {
            return
        }
        const isOpen = popover.classList.contains("open")
        this.closeAllPopovers()
        if (isOpen) {
            return
        }
        const rect = trigger.getBoundingClientRect()
        const popoverWidth = 300
        let left = rect.right - popoverWidth
        let top = rect.bottom + 6
        if (left < 8) {
            left = 8
        }
        if (top + 400 > window.innerHeight) {
            top = rect.top - 406
        }
        popover.style.left = `${left}px`
        popover.style.top = `${top}px`
        popover.classList.add("open")
        popover.setAttribute("aria-hidden", "false")
        trigger.setAttribute("aria-expanded", "true")
    }

    private closePopoverByTrigger(trigger: HTMLElement): void {
        const popoverID = trigger.dataset.popoverClose
        if (!popoverID) {
            return
        }
        const popover = document.getElementById(popoverID)
        popover?.classList.remove("open")
        popover?.setAttribute("aria-hidden", "true")
        trigger.setAttribute("aria-expanded", "false")
    }

    private closeAllPopovers(): void {
        document.querySelectorAll<HTMLElement>(".ide-popover.open").forEach((popover) => {
            popover.classList.remove("open")
            popover.setAttribute("aria-hidden", "true")
            const trigger = document.querySelector<HTMLElement>(`[data-popover="${popover.id}"]`)
            trigger?.setAttribute("aria-expanded", "false")
        })
        document.querySelectorAll<HTMLElement>("[data-popover]").forEach((trigger) => {
            trigger.setAttribute("aria-expanded", "false")
        })
    }
}
