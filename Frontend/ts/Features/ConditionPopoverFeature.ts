import {PopoverEditorFeature} from "@/Features/PopoverEditorFeature"

export class ConditionPopoverFeature extends PopoverEditorFeature {
    protected readonly triggerSelector = '[data-bs-toggle="popover"][data-bs-custom-class="condition-popover"]'
    protected readonly popoverClass = "condition-popover"
    protected readonly templateId = "condition-popover-content"
    protected readonly loadErrorMessage = "Failed to load condition editor"

    protected override prepareRequestURL(pageURL: URL, trigger: HTMLElement): string | null {
        if (trigger.dataset.accessControl) {
            pageURL.searchParams.set("accessControl", trigger.dataset.accessControl)
        }
        return null
    }

    protected override configurePopover(popoverBody: Element): void {
        const form = popoverBody.querySelector<HTMLFormElement>(".condition-popover-form")
        const textarea = form?.querySelector<HTMLTextAreaElement>(".condition-textarea")
        const cancelButton = form?.querySelector<HTMLButtonElement>(".condition-cancel-btn")
        const saveButton = form?.querySelector<HTMLButtonElement>(".condition-save-btn")
        if (!form || !textarea || !cancelButton || !saveButton) {
            return
        }
        cancelButton.addEventListener("click", () => {
            this.hideActivePopover()
        })
        saveButton.addEventListener("click", async (event) => {
            event.preventDefault()
            await this.context.formSubmissionService.submit(form)
            this.hideActivePopover()
        })
        textarea.addEventListener("keydown", (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
                event.preventDefault()
                saveButton.click()
            }
        })
        form.querySelectorAll<HTMLElement>(".sp-chip").forEach((chip) => {
            chip.addEventListener("click", () => {
                this.insertAtCaret(textarea, chip.dataset.copy ?? chip.textContent?.trim() ?? "")
            })
        })
        textarea.focus()
        textarea.setSelectionRange(textarea.value.length, textarea.value.length)
    }

    protected override popoverDidShow(): void {
        document.dispatchEvent(new CustomEvent("view:updated"))
    }

    private insertAtCaret(textarea: HTMLTextAreaElement, text: string): void {
        const start = textarea.selectionStart ?? textarea.value.length
        const end = textarea.selectionEnd ?? start
        textarea.setRangeText(text, start, end, "end")
        textarea.dispatchEvent(new Event("input", {bubbles: true}))
        textarea.focus()
    }
}
