import {Application} from "@/Application/Application"
import {FormSubmissionFeature} from "@/Features/FormSubmissionFeature"
import {HistoryFeature} from "@/Features/HistoryFeature"
import {ModalResetFeature} from "@/Features/ModalResetFeature"
import {NavigationFeature} from "@/Features/NavigationFeature"
import {TooltipFeature} from "@/Features/TooltipFeature"
import {ValidationFeature} from "@/Features/ValidationFeature"
import {WelcomeController} from "@/Controllers/WelcomeController"

export const bootstrapWelcome = (): void => {
    new Application(
        (context) => [
            new HistoryFeature(context),
            new NavigationFeature(context),
            new FormSubmissionFeature(context),
            new ValidationFeature(context),
            new ModalResetFeature(context),
            new TooltipFeature(context),
        ],
        (context) => [new WelcomeController(context)],
    ).start()
}
