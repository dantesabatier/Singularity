import {Feature} from "@/Application/Feature"
import {Popover} from "bootstrap"

export class RolePopoverFeature extends Feature {
    private activePopover: Popover | null = null
    private readonly popoverByElement = new WeakMap<HTMLElement, Popover>()

    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const trigger = target.closest<HTMLElement>('[data-bs-toggle="popover"]')
        if (trigger) {
            event.preventDefault()
            event.stopPropagation()
            event.stopImmediatePropagation()
            void this.openRolePopover(trigger)
            return
        }
        if (!this.activePopover || target.closest(".popover")) {
            return
        }
        this.activePopover.hide()
        this.activePopover = null
    }

    private readonly onDocumentEscape = (event: KeyboardEvent): void => {
        if (event.key !== "Escape" || !this.activePopover) {
            return
        }
        this.activePopover.hide()
        this.activePopover = null
    }

    private readonly onShownPopover = (): void => {
        const popoverBody = document.querySelector(".popover-body")
        if (!popoverBody) {
            return
        }
        const form = popoverBody.querySelector<HTMLFormElement>(".role-popover-form")
        const select = form?.querySelector<HTMLSelectElement>(".role-select")
        const customField = form?.querySelector<HTMLElement>(".role-custom-field")
        const customInput = form?.querySelector<HTMLInputElement>("#scopeName")
        const cancelButton = form?.querySelector<HTMLButtonElement>(".role-cancel-btn")
        const saveButton = form?.querySelector<HTMLButtonElement>(".role-save-btn")
        if (!form || !select || !customField || !cancelButton || !saveButton) {
            return
        }
        select.addEventListener("change", () => {
            if (select.value === "Custom...") {
                customField.style.display = "block"
                customInput?.focus()
                return
            }
            customField.style.display = "none"
        })
        cancelButton.addEventListener("click", () => {
            if (!this.activePopover) {
                return
            }
            this.activePopover.hide()
            this.activePopover = null
        })
        saveButton.addEventListener("click", async (event) => {
            event.preventDefault()
            const value = select.value === "Custom..." ? customInput?.value : select.value
            if (!value || value === "Custom...") {
                customInput?.focus()
                return
            }
            await this.context.formSubmissionService.submit(form)
            if (!this.activePopover) {
                return
            }
            this.activePopover.hide()
            this.activePopover = null
        })
        customInput?.addEventListener("keypress", (event) => {
            if (event.key !== "Enter") {
                return
            }
            event.preventDefault()
            saveButton.click()
        })
        if (!select.disabled) {
            select.focus()
            return
        }
        customInput?.focus()
    }

    public override start(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("keydown", this.onDocumentEscape)
        document.addEventListener("shown.bs.popover", this.onShownPopover as EventListener)
    }

    private async openRolePopover(trigger: HTMLElement): Promise<void> {
        const popover = this.popoverFor(trigger)
        if (this.activePopover === popover) {
            popover.hide()
            this.activePopover = null
            return
        }
        if (this.activePopover) {
            this.activePopover.hide()
            this.activePopover = null
        }

        const pageURL = new URL(window.location.href)
        if (!pageURL.searchParams.has("accessControl")) {
            this.showError(popover, "Please select an access control first")
            return
        }

        trigger.classList.add("loading")
        if (trigger.dataset.role) {
            pageURL.searchParams.set("role", trigger.dataset.role)
        }

        try {
            const response = await this.context.httpClient.get(pageURL.href)
            if (!response.ok) {
                const message = await this.context.httpClient.tryGetErrorMessage(response)
                await this.context.desktopBridge.showErrorBox(message)
                return
            }
            const html = await response.text()
            const doc = new DOMParser().parseFromString(html, "text/html")
            const template = doc.getElementById("roles-popover-content")
            if (!template) {
                this.showError(popover, "Failed to load role editor")
                return
            }
            popover.setContent({".popover-body": template.innerHTML})
            popover.show()
            this.activePopover = popover
        } catch (error) {
            console.error("Failed to load role popover:", error)
            this.showError(popover, "Failed to load role editor")
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
            customClass: "role-popover",
            placement: "auto",
            fallbackPlacements: ["top", "bottom", "left", "right"],
        })
        this.popoverByElement.set(trigger, popover)
        return popover
    }

    private showError(popover: Popover, message: string): void {
        popover.setContent({
            ".popover-body": `<div class="p-3 text-danger"><i class="bi bi-exclamation-triangle"></i><span class="px-2">${message}</span></div>`,
        })
        popover.show()
        this.activePopover = popover
        setTimeout(() => {
            if (this.activePopover !== popover) {
                return
            }
            popover.hide()
            this.activePopover = null
        }, 2000)
    }
}
