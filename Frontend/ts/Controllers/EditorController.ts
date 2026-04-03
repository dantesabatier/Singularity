import {ViewController} from "@/Application/ViewController"
import {EditorSplitViewController} from "@/Controllers/Editor/EditorSplitViewController"
import {EditorCopilotController} from "@/Controllers/Editor/EditorCopilotController"
import {EditorGraphController} from "@/Controllers/Editor/EditorGraphController"

type ManagedReference = {
    objectID?: string
    entityName?: string
}

type RemovableReference = ManagedReference & {
    name?: string
    propertyName?: string
    stringValue?: string
}

export class EditorController extends ViewController {
    private readonly compositeTypeAttributeValue = "2100"
    private readonly splitController = new EditorSplitViewController(this.context)
    private readonly copilotController = new EditorCopilotController(this.context)
    private readonly graphController = new EditorGraphController(this.context)
    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const element = target.closest<HTMLElement>("[data-editor-action]")
        if (!element || !this.supportsCurrentView()) {
            return
        }
        event.stopPropagation()
        const action = element.dataset.editorAction
        switch (action) {
            case "showAboutPanel":
                void this.context.desktopBridge.showAboutPanel()
                return
            case "showPreferences":
                void this.context.desktopBridge.showPreferences()
                return
            case "saveProject":
                void this.saveProject(element)
                return
            case "openSQLViewer":
                void this.openSQLViewer(element)
                return
            case "createSubclass":
                void this.createSubclass(element)
                return
            case "importModel":
                void this.importModel(element)
                return
            case "openEditorProject":
                void this.openEditorProject(element)
                return
            case "addEntity":
                void this.add("Entity", "Entity", element)
                return
            case "addCompositeType":
                void this.add("CompositeType", "CompositeType", element)
                return
            case "addFetchRequestTemplate":
                void this.add("FetchRequestTemplate", "fetchRequestTemplate", element)
                return
            case "addConfiguration":
                void this.add("Configuration", "Configuration", element)
                return
            case "addAttribute":
                void this.add("Attribute", "attribute", element)
                return
            case "addFetchedProperty":
                void this.add("FetchedProperty", "fetchedProperty", element)
                return
            case "addRelationship":
                void this.add("Relationship", "relationship", element)
                return
            case "addFetchIndex":
                void this.add("FetchIndex", "fetchIndex", element)
                return
            case "addFetchIndexElement":
                void this.addFetchIndexElement(element)
                return
            case "addReadableAccessControl":
                void this.add("AccessControl", "Readable", element)
                return
            case "addWritableAccessControl":
                void this.add("AccessControl", "Writable", element)
                return
            case "addRole":
                void this.add("Role", "Role", element)
                return
            case "removeSelection":
                void this.removeSelection(element)
                return
            case "toggleSourcePanel":
                this.splitController.toggleSourcePanel()
                return
            case "toggleSidebarPanel":
                this.splitController.toggleSidebarPanel()
                return
            case "toggleCopilotPanel":
                void this.splitController.toggleCopilotPanel()
                return
            default:
                return
        }
    }
    private readonly onDocumentChange = async (event: Event): Promise<void> => {
        const target = event.target
        if (!(target instanceof Element) || !this.supportsCurrentView()) {
            return
        }
        const select = target.closest<HTMLSelectElement>("select[data-editor-change]")
        if (!select?.form) {
            return
        }
        event.stopPropagation()
        const change = select.dataset.editorChange
        switch (change) {
            case "attributeType":
                this.updateAttributeValueClassName(select)
                await this.context.formSubmissionService.submit(select.form)
                return
            case "accessControlScope":
                await this.context.formSubmissionService.submit(select.form)
                return
            default:
                return
        }
    }

    protected override supportsCurrentView(): boolean {
        return document.getElementById("split-container") !== null
    }

    protected override setup(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("change", this.onDocumentChange)
        this.initializeSubcontrollers()
    }

    protected override viewDidUpdate(): void {
        this.initializeSubcontrollers()
    }

    private async saveProject(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        await this.context.actionDispatcher.dispatch("Save", {project: projectID})
    }

    private async openSQLViewer(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        const url = `${window.location.origin}${this.context.routeBuilder.build("Viewer", {project: projectID})}`
        await this.context.desktopBridge.showWindow({
            url,
            overrideBrowserWindowOptions: {
                width: 1090,
                height: 600,
            },
        })
    }

    private async createSubclass(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        await this.context.actionDispatcher.dispatch("Subclass", {project: projectID})
    }

    private async importModel(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        const modelPath = element.dataset.modelPath
        const result = await this.context.desktopBridge.showOpenDialog(
            "Import Model",
            "Select the model file",
            "Import",
            modelPath,
            ["openFile"],
            [{
                name: "Property list",
                extensions: ["plist"],
            }],
        )
        const filePath = result?.filePaths.find(Boolean)
        if (!filePath) {
            return
        }
        await this.context.actionDispatcher.dispatch("import", {
            project: projectID,
            path: filePath,
        })
    }

    private async openEditorProject(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        await this.context.viewNavigator.push(this.context.routeBuilder.build("Editor", {project: projectID}))
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

    private async add(entity: string, name: string, element: HTMLElement): Promise<void> {
        const parent = this.parseJSON<ManagedReference>(element.dataset.parent)
        if (!parent) {
            return
        }
        switch (entity) {
            case "Entity":
            case "FetchRequestTemplate":
            case "Configuration":
            case "CompositeType":
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    model: parent,
                }, "POST")
                return
            case "Attribute": {
                const body: Record<string, unknown> = {name}
                if (parent.entityName === "Entity") {
                    body.entityProperty = parent
                } else if (parent.entityName === "CompositeType") {
                    body.compositeType = parent
                }
                await this.context.actionDispatcher.dispatch(entity, body)
                return
            }
            case "Relationship":
            case "FetchedProperty":
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    entityProperty: parent,
                })
                return
            case "FetchIndex":
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    entityProperty: parent,
                })
                return
            case "AccessControl":
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    property: parent,
                })
                return
            case "Role":
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    accessControl: parent,
                })
                return
            default:
                return
        }
    }

    private async addFetchIndexElement(element: HTMLElement): Promise<void> {
        const parent = this.parseJSON<ManagedReference>(element.dataset.parent)
        const propertyName = element.dataset.propertyName
        if (!parent || !propertyName) {
            return
        }
        await this.context.actionDispatcher.dispatch("FetchIndexElement", {
            propertyName,
            index: parent,
        })
    }

    private async removeSelection(element: HTMLElement): Promise<void> {
        const item = this.parseJSON<RemovableReference>(element.dataset.item)
        if (!item?.objectID || !item.entityName) {
            return
        }
        const label = item.name ?? item.propertyName ?? item.stringValue ?? "item"
        const result = await this.context.desktopBridge.showMessageBox(`Remove "${label}"?`, "This action cannot be undone.", ["Cancel", "OK"])
        if (!result?.response) {
            return
        }
        await this.context.actionDispatcher.dispatch(item.entityName, {objectID: item.objectID}, "DELETE")
    }

    private updateAttributeValueClassName(select: HTMLSelectElement): void {
        const attributeValueClassName = select.form?.querySelector<HTMLInputElement>("input[name=attributeValueClassName]")
        if (!attributeValueClassName) {
            return
        }
        const selectedLabel = select.options[select.selectedIndex]?.text ?? ""
        attributeValueClassName.value = select.value === this.compositeTypeAttributeValue ? selectedLabel : ""
    }

    private initializeSubcontrollers(): void {
        this.splitController.initialize()
        this.copilotController.initialize()
        this.graphController.initialize()
    }
}
