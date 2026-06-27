import {ApplicationContext} from "@/Application/ApplicationContext"
import {Feature} from "@/Application/Feature"

export class TreeToggleFeature extends Feature {
    public constructor(context: ApplicationContext) {
        super(context)
    }

    public start(): void {
        // Bootstrap owns the visual collapse and fires these on the target after the state
        // settles, so we read the final state from the event instead of racing aria-expanded.
        document.addEventListener("shown.bs.collapse", this.handleToggle)
        document.addEventListener("hidden.bs.collapse", this.handleToggle)
    }

    private readonly handleToggle = (event: Event): void => {
        const group = event.target
        if (!(group instanceof HTMLElement) || !group.id) {
            return
        }
        const toggle = document.querySelector<HTMLElement>(`.tree-toggle[data-bs-target="#${CSS.escape(group.id)}"]`)
        const objectID = toggle?.dataset.id
        if (!objectID) {
            return
        }
        const expanded = event.type === "shown.bs.collapse"
        toggle?.setAttribute("aria-expanded", String(expanded))
        void this.context.httpClient.patch("Entity", {objectID, isExpanded: expanded})
    }
}
