import {Feature} from "@/Application/Feature"
import Sortable, {SortableEvent} from "sortablejs"

export class SortableTableFeature extends Feature {
    public override refresh(): void {
        document.querySelectorAll<HTMLTableElement>("table[data-project][data-entity][data-key]").forEach((table) => {
            const body = table.querySelector<HTMLTableSectionElement>("tbody")
            if (!body || body.dataset.sortableBound === "true") {
                return
            }
            Sortable.create(body, {
                onUpdate: async (event: SortableEvent) => {
                    if (event.oldIndex === null || event.newIndex === null || event.oldIndex === event.newIndex) {
                        return
                    }
                    await this.context.actionDispatcher.dispatch("Reorder", {
                        project: table.dataset.project,
                        entity: table.dataset.entity,
                        key: table.dataset.key,
                        fromIndex: event.oldIndex,
                        toIndex: event.newIndex,
                    })
                },
            })
            body.dataset.sortableBound = "true"
        })
    }
}
