import {Feature} from "@/Application/Feature"

export class ValidationFeature extends Feature {
    private handleSubmit = async (event: Event) => {
        const form = event.target
        if (!(form instanceof HTMLFormElement) || !form.matches(".needs-validation")) {
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
        const target = event.target
        if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement) && !(target instanceof HTMLTextAreaElement)) {
            return
        }
        const isValid = target.checkValidity()
        target.classList.toggle("is-invalid", !isValid)
        target.classList.toggle("is-valid", isValid)
    }

    public override start(): void {
        document.addEventListener("submit", this.handleSubmit, true)
        document.addEventListener("input", this.handleInputChange, true)
        document.addEventListener("change", this.handleInputChange, true)
    }
}
