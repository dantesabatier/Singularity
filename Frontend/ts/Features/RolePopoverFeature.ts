import {PopoverEditorFeature} from "@/Features/PopoverEditorFeature"

export class RolePopoverFeature extends PopoverEditorFeature {
    protected readonly triggerSelector = '[data-bs-toggle="popover"][data-role]'
    protected readonly popoverClass = "role-popover"
    protected readonly templateId = "roles-popover-content"
    protected readonly loadErrorMessage = "Failed to load role editor"

    protected override prepareRequestURL(pageURL: URL, trigger: HTMLElement): string | null {
        if (trigger.dataset.accessControl) {
            pageURL.searchParams.set("accessControl", trigger.dataset.accessControl)
        }
        if (!pageURL.searchParams.has("accessControl")) {
            return "Please select an access control first"
        }
        if (trigger.dataset.role) {
            pageURL.searchParams.set("role", trigger.dataset.role)
        }
        return null
    }

    protected override configurePopover(popoverBody: Element): void {
        const form = popoverBody.querySelector<HTMLFormElement>(".role-popover-form")
        const select = form?.querySelector<HTMLSelectElement>(".role-select")
        const customField = form?.querySelector<HTMLElement>(".role-custom-field")
        const customInput = form?.querySelector<HTMLInputElement>("#scopeName")
        const cancelButton = form?.querySelector<HTMLButtonElement>(".role-cancel-btn")
        const saveButton = form?.querySelector<HTMLButtonElement>(".role-save-btn")
        if (!form || !select || !customField || !cancelButton || !saveButton) {
            return
        }
        const synchronizeCustomField = (): void => {
            const isCustom = select.value === "Custom..."
            customField.style.display = isCustom ? "block" : "none"
            if (customInput) {
                customInput.disabled = !isCustom
            }
        }
        synchronizeCustomField()
        select.addEventListener("change", () => {
            synchronizeCustomField()
            if (select.value !== "Custom...") {
                return
            }
            customInput?.focus()
        })
        cancelButton.addEventListener("click", () => {
            this.hideActivePopover()
        })
        saveButton.addEventListener("click", async (event) => {
            event.preventDefault()
            const value = select.value === "Custom..." ? customInput?.value.trim() : select.value
            if (!value || value === "Custom...") {
                customInput?.focus()
                return
            }
            await this.context.formSubmissionService.submit(form)
            this.hideActivePopover()
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
}
