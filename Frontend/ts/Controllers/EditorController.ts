import {ViewController} from "@/Application/ViewController"
import {EditorSplitViewController} from "@/Controllers/Editor/EditorSplitViewController"
import {EditorChatController} from "./Editor/EditorChatController"
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

type RoleReference = ManagedReference & {
    name?: string
}

type ServiceError = {
    localizedDescription?: string | null
    localizedFailureReason?: string | null
    localizedRecoverySuggestion?: string | null
}

export class EditorController extends ViewController {
    private readonly compositeTypeAttributeValue = "2100"
    private readonly splitController = new EditorSplitViewController(this.context)
    private readonly copilotController = new EditorChatController(this.context)
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
            case "showHelp":
                void this.context.desktopBridge.showHelp()
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
            case "extractLocalizables":
                void this.extractLocalizables(element)
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
            case "unassignRole":
                void this.unassignRole(element)
                return
            case "removeSelection":
                void this.removeSelection(element)
                return
            case "deleteConversation":
                void this.deleteConversation(element)
                return
            case "toggleSourcePanel":
                this.splitController.toggleSourcePanel()
                return
            case "toggleSidebarPanel":
                this.splitController.toggleSidebarPanel()
                return
            case "selectConversation":
                void this.selectConversation(element)
                return
            case "newConversation":
                void this.newConversation()
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

    private async extractLocalizables(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        await this.context.actionDispatcher.dispatch("localize", {project: projectID})
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
                name: "Managed Object Model",
                extensions: ["mom"],
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
        const currentProjectID = new URL(window.location.href).searchParams.get("project")
        const cleanUrl = this.context.routeBuilder.build("Editor", {project: projectID})
        if (currentProjectID && currentProjectID !== projectID) {
            const freshUrl = new URL(cleanUrl, window.location.origin)
            freshUrl.searchParams.set("fresh", "1")
            const replaced = await this.context.viewNavigator.replace(freshUrl.href)
            if (replaced) {
                history.pushState({url: cleanUrl}, "", cleanUrl)
            }
        } else {
            await this.context.viewNavigator.push(cleanUrl)
        }
    }

    // The service replies with the error described ({error: {localizedDescription, ...}}); propagate it as-is since showErrorBox presents exactly those fields.
    private async requireOK(response: Response): Promise<Response> {
        if (response.ok) {
            return response
        }
        const described = await response.clone().json().then(
            (body: {error?: ServiceError}) => body?.error,
            () => undefined,
        )
        throw described ?? {
            localizedDescription: await this.context.httpClient.tryGetErrorMessage(response) ?? `${response.status} ${response.statusText}`,
        }
    }

    // The IPC bridge clones the payload, so an Error does not survive the trip with its message.
    private async presentError(error: unknown): Promise<void> {
        await this.context.desktopBridge.showErrorBox(
            error instanceof Error ? {localizedDescription: error.message} : error,
        )
    }

    // Core Data compares the incoming snapshot with the current one to detect the change, so the relationship travels with its elementary attributes: a body with only the objectID is saved without ever assigning the conversation.
    private projectSelectionSnapshot(projectID: string, conversationID: string): Record<string, unknown> {
        const panel = document.getElementById("ai-chat-panel")
        const name = panel instanceof HTMLElement ? panel.dataset.projectName : undefined
        return {
            entityName: "Project",
            objectID: projectID,
            ...(name === undefined ? {} : {name}),
            selectedConversation: {entityName: "Conversation", objectID: conversationID},
        }
    }

    private async selectConversation(element: HTMLElement): Promise<void> {
        const conversationID = element.dataset.conversationId
        if (!conversationID) {
            return
        }
        const nextUrl = new URL(window.location.href)
        nextUrl.searchParams.delete("conversation")
        const projectID = nextUrl.searchParams.get("project")
        try {
            if (projectID) {
                await this.requireOK(await this.context.httpClient.patch("/Project", this.projectSelectionSnapshot(projectID, conversationID)))
            }
            await this.context.viewNavigator.navigatePartial(nextUrl, {
                beforeReplace: (url) => this.updateSourceListActiveState(url),
            })
        } catch (error) {
            await this.presentError(error)
        }
    }

    private updateSourceListActiveState(nextUrl: URL): void {
        document.querySelectorAll<HTMLElement>("#source [data-href]").forEach((el) => {
            const elHref = el.dataset.href
            if (!elHref) {
                return
            }
            const elUrl = new URL(elHref, window.location.origin)
            const isActive = [...elUrl.searchParams.entries()]
                .filter(([k]) => k !== "project")
                .every(([k, v]) => nextUrl.searchParams.get(k) === v)
            el.classList.toggle("active", isActive)
        })
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
        if (raw === undefined) {
            return undefined
        }
        const position = Number(raw)
        return Number.isInteger(position) && position >= 0 ? position : undefined
    }

    private async add(entity: string, name: string, element: HTMLElement): Promise<void> {
        const parent = this.parseJSON<ManagedReference>(element.dataset.parent)
        const position = this.parsePosition(element.dataset.position)
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
                })
                return
            case "Attribute": {
                const body: Record<string, unknown> = {name, position}
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
                    position,
                    entityProperty: parent,
                })
                return
            case "FetchIndex":
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    entityProperty: parent,
                })
                return
            case "AccessControl": {
                const body: Record<string, unknown> = {name}
                if (parent.entityName === "Entity") {
                    body.entityProperty = parent
                } else {
                    body.property = parent
                }
                await this.context.actionDispatcher.dispatch(entity, body)
                return
            }
            case "Role": {
                // Roles are shared across access controls: the relationship is many-to-many, and the popover renames a Role in place for every access control holding it. Creating a new row for a name the model already carries would fork that identity, so assign the existing Role by PATCHing the access control's relationship and only POST a new Role when the name is genuinely new.
                const existing = (this.parseJSON<RoleReference[]>(element.dataset.availableRoles) ?? [])
                    .find((role) => role.name === name)
                if (existing) {
                    await this.assignRole(parent, existing)
                    return
                }
                const model = this.parseJSON<ManagedReference>(element.dataset.model)
                await this.context.actionDispatcher.dispatch(entity, {
                    name,
                    model,
                    accessControls: [parent],
                })
                return
            }
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

    // Adds an already-existing Role to an access control by rewriting the whole relationship: the roles it already holds plus the new one. Mirrors unassignRole, which sends the ones that remain.
    private async assignRole(parent: RoleReference, role: RoleReference): Promise<void> {
        const card = document.getElementById(`accessControl-${parent.objectID}`)
        const current = Array.from(card?.querySelectorAll<HTMLElement>(".role-badge-delete") ?? [])
            .map((badge) => badge.dataset.role)
            .filter((id): id is string => !!id)
        if (current.includes(String(role.objectID))) {
            return
        }
        const roles = Array.from(card?.querySelectorAll<HTMLElement>(".role-badge-delete") ?? [])
            .filter((badge) => !!badge.dataset.role)
            .map((badge) => ({
                objectID: badge.dataset.role as string,
                entityName: "Role",
                name: badge.parentElement?.querySelector(".role-badge")?.textContent?.trim() ?? "",
            }))
            .concat([{objectID: String(role.objectID), entityName: "Role", name: role.name ?? ""}])
        await this.context.actionDispatcher.dispatch("AccessControl", {
            objectID: parent.objectID,
            entityName: parent.entityName ?? "AccessControl",
            name: parent.name ?? "",
            roles,
        }, "PATCH")
    }

    private async unassignRole(element: HTMLElement): Promise<void> {
        const accessControlID = element.dataset.accessControl
        const roleID = element.dataset.role
        if (!accessControlID || !roleID) {
            return
        }
        const label = element.parentElement?.querySelector(".role-badge")?.textContent?.trim() ?? "role"
        const result = await this.context.desktopBridge.showMessageBox(`Remove "${label}"?`, "The role stays in the model and can be assigned again.", ["Cancel", "OK"])
        if (!result?.response) {
            return
        }
        const card = document.getElementById(`accessControl-${accessControlID}`)
        const roles = Array.from(card?.querySelectorAll<HTMLElement>(".role-badge-delete") ?? [])
            .filter((badge) => !!badge.dataset.role && badge.dataset.role !== roleID)
            .map((badge) => ({
                objectID: badge.dataset.role as string,
                entityName: "Role",
                name: badge.parentElement?.querySelector(".role-badge")?.textContent?.trim() ?? "",
            }))
        await this.context.actionDispatcher.dispatch("AccessControl", {
            objectID: accessControlID,
            entityName: card?.dataset.entityName ?? "AccessControl",
            name: card?.dataset.name ?? "",
            roles,
        }, "PATCH")
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

    private async newConversation(): Promise<void> {
        const panel = document.getElementById("ai-chat-panel")
        if (!(panel instanceof HTMLElement)) {
            return
        }
        const projectID = panel.dataset.projectObjectId
        if (!projectID) {
            return
        }
        const existingTitles = new Set(
            Array.from(document.querySelectorAll<HTMLElement>("[data-editor-action='selectConversation'] span"))
                .map(el => el.textContent?.trim() ?? "")
        )
        const defaultTitle = "New conversation"
        let title = defaultTitle
        let counter = 1
        while (existingTitles.has(title)) {
            title = `${defaultTitle} ${counter}`
            counter++
        }
        const provider = panel.dataset.aiProvider ?? "anthropic"
        try {
            const response = await this.requireOK(await this.context.httpClient.post("/Conversation", {
                title,
                project: {objectID: projectID},
                provider,
            }))
            const data = await response.json() as {objectID?: string | number}
            const conversationID = data.objectID ? String(data.objectID) : null
            if (!conversationID) {
                throw new Error("The created conversation did not return an objectID.")
            }
            await this.requireOK(await this.context.httpClient.patch("/Project", this.projectSelectionSnapshot(projectID, conversationID)))
        } catch (error) {
            await this.presentError(error)
            return
        }
        const nextUrl = new URL(window.location.href)
        nextUrl.searchParams.delete("conversation")
        await this.context.viewNavigator.navigatePartial(nextUrl, {
            beforeReplace: (url) => this.updateSourceListActiveState(url),
        })
    }

    private async deleteConversation(element: HTMLElement): Promise<void> {
        const conversationID = element.dataset.conversationId
        if (!conversationID) {
            return
        }
        const result = await this.context.desktopBridge.showMessageBox("Delete conversation?", "This action cannot be undone.", ["Cancel", "OK"])
        if (!result?.response) {
            return
        }
        const panel = document.getElementById("ai-chat-panel")
        const projectID = panel instanceof HTMLElement ? panel.dataset.projectObjectId : null
        const messagesContainer = document.getElementById("chat-messages")
        const selectedID = messagesContainer instanceof HTMLElement ? messagesContainer.dataset.conversationId : null
        try {
            if (projectID && selectedID === conversationID) {
                const snapshot = this.projectSelectionSnapshot(projectID, conversationID)
                await this.requireOK(await this.context.httpClient.patch("/Project", {...snapshot, selectedConversation: null}))
            }
            await this.requireOK(await this.context.httpClient.delete("/Conversation", {objectID: conversationID}))
        } catch (error) {
            await this.presentError(error)
            return
        }
        const nextUrl = new URL(window.location.href)
        nextUrl.searchParams.delete("conversation")
        await this.context.viewNavigator.push(nextUrl.href, "reload")
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
