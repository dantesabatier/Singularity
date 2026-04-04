import Split from "split.js"
import cytoscape from "cytoscape"
import cytoscapeDagre from "cytoscape-dagre"
import registerNodeHtmlLabel from "cytoscape-node-html-label"
import * as dagre from "dagre"
import {Application} from "@/Application/Application"
import {AutosaveFeature} from "@/Features/AutosaveFeature"
import {ColorPickerFeature} from "@/Features/ColorPickerFeature"
import {FormSubmissionFeature} from "@/Features/FormSubmissionFeature"
import {HistoryFeature} from "@/Features/HistoryFeature"
import {ModalResetFeature} from "@/Features/ModalResetFeature"
import {NavigationFeature} from "@/Features/NavigationFeature"
import {PopoverFeature} from "@/Features/PopoverFeature"
import {PredicateHighlighterFeature} from "@/Features/PredicateHighlighterFeature"
import {RolePopoverFeature} from "@/Features/RolePopoverFeature"
import {SortableTableFeature} from "@/Features/SortableTableFeature"
import {TooltipFeature} from "@/Features/TooltipFeature"
import {TreeToggleFeature} from "@/Features/TreeToggleFeature"
import {ValidationFeature} from "@/Features/ValidationFeature"
import {EditorController} from "@/Controllers/EditorController"

export const bootstrapEditor = (): void => {
    cytoscape.use(cytoscapeDagre)
    registerNodeHtmlLabel(cytoscape)
    window.Split = Split
    window.cytoscape = cytoscape
    window.cytoscapeDagre = cytoscapeDagre
    window.dagre = dagre
    new Application(
        (context) => [
            new HistoryFeature(context),
            new NavigationFeature(context),
            new TreeToggleFeature(context),
            new AutosaveFeature(context),
            new FormSubmissionFeature(context),
            new ValidationFeature(context),
            new ModalResetFeature(context),
            new TooltipFeature(context),
            new PopoverFeature(context),
            new PredicateHighlighterFeature(context),
            new RolePopoverFeature(context),
            new SortableTableFeature(context),
            new ColorPickerFeature(context),
        ],
        (context) => [new EditorController(context)],
    ).start()
}
