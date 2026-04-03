import {ActionDispatcher} from "@/Services/ActionDispatcher"
import {FormSerializer} from "@/Services/FormSerializer"

export class FormSubmissionService {
    public constructor(private readonly formSerializer: FormSerializer, private readonly actionDispatcher: ActionDispatcher) {
    }

    public async submit(form: HTMLFormElement): Promise<boolean> {
        const parsed = this.formSerializer.serialize(form)
        return await this.actionDispatcher.dispatch(form.action, parsed.body, parsed.method)
    }
}
