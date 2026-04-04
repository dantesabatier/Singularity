import "db-viewer-component"
import {Application} from "@/Application/Application"
import {HistoryFeature} from "@/Features/HistoryFeature"
import {NavigationFeature} from "@/Features/NavigationFeature"
import {TooltipFeature} from "@/Features/TooltipFeature"
import {ViewerController} from "@/Controllers/ViewerController"

export const bootstrapViewer = (): void => {
    new Application(
        (context) => [
            new HistoryFeature(context),
            new NavigationFeature(context),
            new TooltipFeature(context),
        ],
        (context) => [new ViewerController(context)],
    ).start()
}
