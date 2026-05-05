import {Application} from "@/Application/Application"
import {AutosaveFeature} from "@/Features/AutosaveFeature"
import {FormSubmissionFeature} from "@/Features/FormSubmissionFeature"
import {ValidationFeature} from "@/Features/ValidationFeature"
import {AIProvidersController} from "@/Controllers/AIProvidersController"
import {PreferencesController} from "@/Controllers/PreferencesController"

export const bootstrapPreferences = (): void => {
    new Application(
        (context) => [
            new AutosaveFeature(context),
            new FormSubmissionFeature(context),
            new ValidationFeature(context),
        ],
        (context) => [new PreferencesController(context), new AIProvidersController(context)],
    ).start()
}
