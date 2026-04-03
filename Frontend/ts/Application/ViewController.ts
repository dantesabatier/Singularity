import {ApplicationContext} from "@/Application/ApplicationContext"

export abstract class ViewController {
    private hasInitialized = false

    public constructor(protected readonly context: ApplicationContext) {
    }

    public initialize(): void {
        if (!this.supportsCurrentView()) {
            return
        }
        if (this.hasInitialized) {
            this.viewDidUpdate()
            return
        }
        this.setup()
        this.hasInitialized = true
    }

    protected viewDidUpdate(): void {
    }

    protected abstract supportsCurrentView(): boolean

    protected abstract setup(): void
}
