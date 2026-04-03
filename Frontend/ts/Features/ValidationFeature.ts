import {Feature} from "@/Application/Feature"

export class ValidationFeature extends Feature {
    private handleSubmit = async (event: Event) => {
        const form = event.target as HTMLFormElement
        if (!form || !form.matches(".needs-validation")) {
            return
        }
        event.preventDefault()
        if (!form.checkValidity()) {
            event.stopPropagation()
            form.classList.add("was-validated")
            return
        }
        if (!form.classList.contains("form-new-project")) {
            await this.context.formSubmissionService.submit(form)
        }
    }

    private handleInputChange = (event: Event): void => {
        const target = event.target as
            | HTMLInputElement
            | HTMLSelectElement
            | HTMLTextAreaElement
        if (!target) {
            return
        }
        target.classList.toggle("is-invalid", !target.checkValidity())
        target.classList.toggle("is-valid", target.checkValidity())
    }

    public override refresh(): void {
        document.addEventListener("submit", this.handleSubmit, true)
        document.addEventListener("input", this.handleInputChange, true)
        document.addEventListener("change", this.handleInputChange, true)
    }
}
