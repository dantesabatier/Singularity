import {Feature} from "@/Application/Feature"

export class AutosaveFeature extends Feature {
    private readonly onDocumentChange = async (event: Event) => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const checkboxForm = target.closest<HTMLInputElement>("input[type=checkbox]")?.form
        if (checkboxForm) {
            event.stopPropagation()
            await this.context.formSubmissionService.submit(checkboxForm)
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
        document.addEventListener("change", this.onDocumentChange)
    }
}
