import {Feature} from "@/Application/Feature"

export class AutosaveFeature extends Feature {
    private readonly onDocumentClick = async (event: MouseEvent) => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        if (target.closest("select[data-autosave=true]")) {
            event.stopPropagation()
            return
        }
        const checkboxForm = target.closest<HTMLInputElement>("input[type=checkbox]")?.form
        if (!checkboxForm) {
            return
        }
        event.stopPropagation()
        await this.context.formSubmissionService.submit(checkboxForm)
    }

    private readonly onDocumentChange = async (event: Event) => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const selectForm = target.closest<HTMLSelectElement>("select[data-autosave=true]")?.form
        if (selectForm) {
            event.stopPropagation()
            await this.context.formSubmissionService.submit(selectForm)
            return
        }
        const textareaForm = target.closest<HTMLTextAreaElement>("textarea")?.form
        if (!textareaForm) {
            return
        }
        event.stopPropagation()
        await this.context.formSubmissionService.submit(textareaForm)
    }

    public override start(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("change", this.onDocumentChange)
    }
}
