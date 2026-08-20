import Split from "split.js"
import {Application} from "@/Application/Application"
import {AutosaveFeature} from "@/Features/AutosaveFeature"
import {FormSubmissionFeature} from "@/Features/FormSubmissionFeature"
import {HistoryFeature} from "@/Features/HistoryFeature"
import {NavigationFeature} from "@/Features/NavigationFeature"
import {PopoverFeature} from "@/Features/PopoverFeature"
import {TooltipFeature} from "@/Features/TooltipFeature"
import {ValidationFeature} from "@/Features/ValidationFeature"
import {MappingController} from "@/Controllers/MappingController"

export const bootstrapMapping = (): void => {
    window.Split = Split
    new Application(
        (context) => [
            new HistoryFeature(context),
            new NavigationFeature(context),
            new AutosaveFeature(context),
            new FormSubmissionFeature(context),
            new ValidationFeature(context),
            new TooltipFeature(context),
            new PopoverFeature(context),
        ],
        (context) => [new MappingController(context)],
    ).start()
}
