import {ApplicationContext} from "@/Application/ApplicationContext"
import {Feature} from "@/Application/Feature"
import {ViewController} from "@/Application/ViewController"

export class Application {
    private readonly context = new ApplicationContext()
    private isStarted = false
    private readonly features: readonly Feature[]
    private readonly controllers: readonly ViewController[]

    public constructor(featureFactory: (context: ApplicationContext) => readonly Feature[], controllerFactory: (context: ApplicationContext) => readonly ViewController[]) {
        this.features = featureFactory(this.context)
        this.controllers = controllerFactory(this.context)
    }

    public start(): void {
        const initialize = (): void => {
            if (this.isStarted) {
                return
            }
            this.isStarted = true
            this.features.forEach((feature) => feature.start())
            this.controllers.forEach((controller) => controller.initialize())
        }
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initialize, {once: true})
        } else {
            initialize()
        }
        document.addEventListener("view:updated", () => {
            this.features.forEach((feature) => feature.refresh())
            this.controllers.forEach((controller) => controller.initialize())
        })
    }
}
