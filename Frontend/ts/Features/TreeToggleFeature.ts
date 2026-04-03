import {ApplicationContext} from "@/Application/ApplicationContext"
import {Feature} from "@/Application/Feature"

export class TreeToggleFeature extends Feature {
    public constructor(context: ApplicationContext) {
        super(context)
    }

    public start(): void {
        document.addEventListener("click", this.handleClick)
    }

    private readonly handleClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const toggle = target.closest<HTMLElement>("[data-tree-toggle]")
        if (!toggle) {
            return
        }
        event.preventDefault()
        const selector = toggle.dataset.treeToggle
        if (!selector) {
            return
        }
        const node = document.querySelector<HTMLElement>(selector)
        if (!node) {
            return
        }
        const expanded = toggle.getAttribute("aria-expanded") === "true"
        toggle.setAttribute("aria-expanded", String(!expanded))
        node.hidden = expanded
    }
}
