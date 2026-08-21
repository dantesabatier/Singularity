import {ViewController} from "@/Application/ViewController"

type ManagedReference = {
    objectID?: string
    entityName?: string
}

type RemovableReference = ManagedReference & {
    name?: string
}

export class MappingController extends ViewController {
    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const element = target.closest<HTMLElement>("[data-mapping-action]")
        if (!element || !this.supportsCurrentView()) {
            return
        }
        event.stopPropagation()
        const action = element.dataset.mappingAction
        switch (action) {
            case "seedModelMap":
                void this.seedModelMap(element)
                return
            case "addEntityMap":
                void this.addEntityMap(element)
                return
            case "addAttributeMap":
                void this.addPropertyMap(element, "attributeEntityMap")
                return
            case "addRelationshipMap":
                void this.addPropertyMap(element, "relationshipEntityMap")
                return
            case "removeSelection":
                void this.removeSelection(element)
                return
            case "save":
                void this.save()
                return
            case "upgradeModel":
                void this.upgradeModel(element)
                return
            default:
                return
        }
    }

    protected override supportsCurrentView(): boolean {
        return document.querySelector("#main[data-view='mapping']") !== null
    }

    protected override setup(): void {
        document.addEventListener("click", this.onDocumentClick)
    }

    private get projectID(): string | undefined {
        return document.getElementById("mapping-runtime")?.dataset.projectObjectId
    }

    private parseJSON<T>(raw: string | undefined): T | undefined {
        if (!raw) {
            return undefined
        }
        try {
            return JSON.parse(raw) as T
        } catch {
            return undefined
        }
    }

    private parsePosition(raw: string | undefined): number | undefined {
        if (!raw) {
            return undefined
        }
        const position = Number(raw)
        return Number.isInteger(position) && position >= 0 ? position : undefined
    }

    private async seedModelMap(element: HTMLElement): Promise<void> {
        const version = element.dataset.version
        const projectID = this.projectID
        if (!version || !projectID) {
            return
        }
        await this.context.actionDispatcher.dispatch(`Seed?project=${encodeURIComponent(projectID)}`, {version})
    }

    private async addEntityMap(element: HTMLElement): Promise<void> {
        const parent = this.parseJSON<ManagedReference>(element.dataset.parent)
        if (!parent) {
            return
        }
        await this.context.actionDispatcher.dispatch("EntityMap", {
            modelMap: parent,
            position: this.parsePosition(element.dataset.position),
            type: 0,
        })
    }

    private async addPropertyMap(element: HTMLElement, key: string): Promise<void> {
        const parent = this.parseJSON<ManagedReference>(element.dataset.parent)
        if (!parent) {
            return
        }
        await this.context.actionDispatcher.dispatch("PropertyMap", {
            [key]: parent,
            name: "",
            position: this.parsePosition(element.dataset.position),
        })
    }

    private async removeSelection(element: HTMLElement): Promise<void> {
        const item = this.parseJSON<RemovableReference>(element.dataset.item)
        if (!item?.objectID || !item.entityName) {
            return
        }
        const label = item.name ?? "item"
        const result = await this.context.desktopBridge.showMessageBox(`Remove "${label}"?`, "This action cannot be undone.", ["Cancel", "OK"])
        if (!result?.response) {
            return
        }
        await this.context.actionDispatcher.dispatch(item.entityName, {objectID: item.objectID}, "DELETE")
    }

    private async upgradeModel(element: HTMLElement): Promise<void> {
        const item = this.parseJSON<ManagedReference>(element.dataset.item)
        const projectID = this.projectID
        if (!item?.objectID || !projectID) {
            return
        }
        const result = await this.context.desktopBridge.showMessageBox(
            "Upgrade the store to this version?",
            "The mapping model is written to disk and the store is handed over to the version it arrives at. The migration runs the next time the store opens.",
            ["Cancel", "OK"],
        )
        if (!result?.response) {
            return
        }
        await this.context.actionDispatcher.dispatch(`Upgrade?project=${encodeURIComponent(projectID)}&modelMap=${encodeURIComponent(item.objectID)}`)
    }

    private async save(): Promise<void> {
        const projectID = this.projectID
        if (!projectID) {
            return
        }
        await this.context.actionDispatcher.dispatch("Save", {project: projectID})
    }
}
