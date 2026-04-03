import {AutosaveFeature} from "@/Features/AutosaveFeature"
import {FormSubmissionFeature} from "@/Features/FormSubmissionFeature"
import {HistoryFeature} from "@/Features/HistoryFeature"
import {ModalResetFeature} from "@/Features/ModalResetFeature"
import {NavigationFeature} from "@/Features/NavigationFeature"
import {PopoverFeature} from "@/Features/PopoverFeature"
import {PredicateHighlighterFeature} from "@/Features/PredicateHighlighterFeature"
import {ColorPickerFeature} from "@/Features/ColorPickerFeature"
import {RolePopoverFeature} from "@/Features/RolePopoverFeature"
import {SortableTableFeature} from "@/Features/SortableTableFeature"
import {TooltipFeature} from "@/Features/TooltipFeature"
import {TreeToggleFeature} from "@/Features/TreeToggleFeature"
import {ValidationFeature} from "@/Features/ValidationFeature"
import {ApplicationContext} from "@/Application/ApplicationContext"
import {Feature} from "@/Application/Feature"

export class Application {
    private readonly context = new ApplicationContext()
    private isStarted = false
    private readonly features: readonly Feature[] = [
        new HistoryFeature(this.context),
        new NavigationFeature(this.context),
        new TreeToggleFeature(this.context),
        new AutosaveFeature(this.context),
        new FormSubmissionFeature(this.context),
        new ValidationFeature(this.context),
        new ModalResetFeature(this.context),
        new TooltipFeature(this.context),
        new PopoverFeature(this.context),
        new PredicateHighlighterFeature(this.context),
        new RolePopoverFeature(this.context),
        new SortableTableFeature(this.context),
        new ColorPickerFeature(this.context),
    ]

    public start(): void {
        const initialize = (): void => {
            if (this.isStarted) {
                return
            }
            this.isStarted = true
            this.features.forEach((feature) => feature.start())
        }
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initialize, {once: true})
        } else {
            initialize()
        }
        document.addEventListener("view:updated", () => this.features.forEach((feature) => feature.refresh()))
    }
}
