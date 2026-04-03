import {ApplicationContext} from "./ApplicationContext"

export abstract class Feature {
    public constructor(protected readonly context: ApplicationContext) {
    }

    public start(): void {
        this.refresh()
    }

    public refresh(): void {
    }
}
