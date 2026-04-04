import {Application} from "@/Application/Application"
import {TooltipFeature} from "@/Features/TooltipFeature"
import {AboutController} from "@/Controllers/AboutController"

export const bootstrapAbout = (): void => {
    new Application(
        (context) => [new TooltipFeature(context)],
        (context) => [new AboutController(context)],
    ).start()
}
