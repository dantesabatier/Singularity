import {Feature} from "@/Application/Feature"

export class HistoryFeature extends Feature {
    private readonly onPopState = async (event: PopStateEvent) => {
        const url = event.state?.url ?? window.location.href
        await this.context.viewNavigator.replace(url)
    }

    public override start(): void {
        window.addEventListener("popstate", this.onPopState)
    }
}
