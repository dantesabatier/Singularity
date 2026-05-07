import {DesktopBridge} from "@/Services/DesktopBridge"
import {HttpClient} from "@/Services/HttpClient"
import {ViewNavigator} from "@/Services/ViewNavigator"

type Endpoint =
    | "AccessControl"
    | "Attribute"
    | "CompositeType"
    | "Configuration"
    | "Entity"
    | "FetchIndex"
    | "FetchIndexElement"
    | "FetchRequestTemplate"
    | "FetchedProperty"
    | "Relationship"
    | "Reorder"
    | "Role"
    | "Save"
    | "Subclass"
    | "Synchronize"
    | "UniquenessConstraint"
    | "import"

export class ActionDispatcher {
    public constructor(private readonly httpClient: HttpClient, private readonly desktopBridge: DesktopBridge, private readonly viewNavigator: ViewNavigator) {
    }

    public async dispatch(action: string, body?: unknown, method = "POST"): Promise<boolean> {
        const location = new URL(window.location.href)
        await this.desktopBridge.setProgressBar(1.1)
        let response: Response
        try {
            response = await this.httpClient.request(action, {
                method,
                body,
            })
        } catch (error) {
            await this.desktopBridge.showErrorBox(error)
            await this.viewNavigator.replace(location.href, "reload")
            await this.desktopBridge.setProgressBar(-1)
            return false
        }

        if (!response.ok) {
            const message = await this.httpClient.tryGetErrorMessage(response)
            await this.desktopBridge.showErrorBox(message)
            await this.viewNavigator.replace(location.href, "reload")
            await this.desktopBridge.setProgressBar(-1)
            return false
        }
        const normalizedMethod = method.toUpperCase()
        const endpoint = this.resolveEndpoint(action)
        await this.applyRedirect(response, normalizedMethod, endpoint, location)
        await this.viewNavigator.push(location.href, "reload")
        await this.desktopBridge.setProgressBar(-1)
        return true
    }

    private resolveEndpoint(action: string): Endpoint | undefined {
        const url = URL.canParse(action) ? new URL(action, window.location.origin) : undefined
        const endpoint = url?.pathname.replace(/^\//, "") ?? action.replace(/^\//, "")
        return endpoint as Endpoint
    }

    private async applyRedirect(response: Response, method: string, endpoint: Endpoint | undefined, location: URL): Promise<void> {
        if (method !== "POST" && method !== "DELETE") {
            return
        }
        const keys = this.resolveLocationKeys(endpoint, location)
        const values = keys.map((key) => `${key}=${location.searchParams.get(key)}`)
        if (method === "POST") {
            const objectID = await this.resolveCreatedObjectID(response)
            const entityKey = this.resolveCreatedEntityKey(endpoint)
            if (objectID && entityKey) {
                values.push(`${entityKey}=${objectID}`)
            }
        }
        if (values.length > 0) {
            location.search = values.join("&")
        }
    }

    private resolveLocationKeys(endpoint: Endpoint | undefined, location: URL): string[] {
        switch (endpoint) {
            case "Entity":
            case "FetchRequestTemplate":
            case "Configuration":
            case "CompositeType":
                return ["project"]
            case "Attribute":
                if (location.searchParams.get("entity")) {
                    return ["project", "entity"]
                }
                if (location.searchParams.get("composite")) {
                    return ["project", "composite"]
                }
                return ["project"]
            case "Relationship":
            case "FetchedProperty":
            case "FetchIndex":
            case "UniquenessConstraint":
                return ["project", "entity"]
            case "FetchIndexElement":
                return ["project", "entity", "index"]
            case "AccessControl":
                return ["project", "entity", "property"]
            default:
                return []
        }
    }

    private async resolveCreatedObjectID(response: Response): Promise<string | undefined> {
        if (response.status !== 200 && response.status !== 201) {
            return undefined
        }

        try {
            const json = await response.json() as { objectID?: string | number }
            const id = json.objectID
            if (id === undefined || id === null || id === "") {
                return undefined
            }
            const str = String(id)
            return str.length > 0 ? str : undefined
        } catch {
            return undefined
        }
    }

    private resolveCreatedEntityKey(endpoint: Endpoint | undefined): string | undefined {
        switch (endpoint) {
            case "Entity":
                return "entity"
            case "FetchRequestTemplate":
                return "fetchRequest"
            case "Configuration":
                return "configuration"
            case "CompositeType":
                return "composite"
            case "Attribute":
            case "Relationship":
            case "FetchedProperty":
                return "property"
            case "FetchIndex":
                return "index"
            case "UniquenessConstraint":
                return "constraint"
            case "FetchIndexElement":
                return "element"
            case "AccessControl":
                return "accessControl"
            default:
                return undefined
        }
    }
}
