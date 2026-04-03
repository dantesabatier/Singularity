import {ActionDispatcher} from "@/Services/ActionDispatcher"
import {DesktopBridge} from "@/Services/DesktopBridge"
import {FormSerializer} from "@/Services/FormSerializer"
import {FormSubmissionService} from "@/Services/FormSubmissionService"
import {HttpClient} from "@/Services/HttpClient"
import {RouteBuilder} from "@/Services/RouteBuilder"
import {ViewCache} from "@/Services/ViewCache"
import {ViewNavigator} from "@/Services/ViewNavigator"

export class ApplicationContext {
    public readonly routeBuilder = new RouteBuilder()
    public readonly httpClient = new HttpClient()
    public readonly viewCache = new ViewCache()
    public readonly desktopBridge = new DesktopBridge()
    public readonly viewNavigator = new ViewNavigator(this.httpClient, this.viewCache, this.desktopBridge)
    public readonly formSerializer = new FormSerializer()
    public readonly actionDispatcher = new ActionDispatcher(this.httpClient, this.viewCache, this.desktopBridge, this.viewNavigator)
    public readonly formSubmissionService = new FormSubmissionService(this.formSerializer, this.actionDispatcher)
}
