import {Feature} from "@/Application/Feature"
import JSColor from "jscolor"

export class ColorPickerFeature extends Feature {
    public override refresh(): void {
        document.querySelectorAll<HTMLElement>(".color-picker").forEach(el => {
            const picker = new JSColor(el, {hideOnPaletteClick: true})
            picker.onChange = async () => {
                await this.context.actionDispatcher.dispatch("Project", {
                    objectID: el.dataset.project,
                    color: picker.toHEXString()
                }, "PATCH")
            }
        })
    }
}
