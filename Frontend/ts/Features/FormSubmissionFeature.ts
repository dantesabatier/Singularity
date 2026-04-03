import {Feature} from "@/Application/Feature"

export class FormSubmissionFeature extends Feature {
    private readonly onDocumentSubmit = async (event: Event) => {
        const form = event.target
        if (!(form instanceof HTMLFormElement)) {
            return
        }
        if (form.matches("form.needs-validation, form.form-new-project")) {
            return
        }
        event.preventDefault()
        await this.context.formSubmissionService.submit(form)
    }

    public override start(): void {
        document.addEventListener("submit", this.onDocumentSubmit)
    }
}
