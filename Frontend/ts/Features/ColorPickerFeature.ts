import {Feature} from "@/Application/Feature"
import iro from "@jaames/iro"

export class ColorPickerFeature extends Feature {
    private readonly panel: HTMLDivElement = (() => {
        const panel = document.createElement("div")
        panel.style.position = "fixed"
        panel.style.zIndex = "5000"
        panel.style.display = "none"
        panel.style.padding = "8px"
        panel.style.borderRadius = "8px"
        panel.style.backgroundColor = "var(--bg-secondary)"
        panel.style.border = "1px solid var(--border-color)"
        panel.style.boxShadow = "var(--shadow-lg)"
        document.body.appendChild(panel)
        return panel
    })()
    private readonly picker = iro.ColorPicker(this.panel, {
        width: 180,
        color: "#3b82f6",
        layoutDirection: "vertical",
        borderWidth: 1,
        borderColor: "#3d3f41",
    })
    private activeElement: HTMLElement | null = null
    private activeProjectID: string | null = null
    private readonly onDocumentPointerDown = (event: PointerEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        if (this.panel.contains(target) || (this.activeElement !== null && this.activeElement.contains(target))) {
            return
        }
        this.hide()
    }

    public override start(): void {
        this.picker.on("color:change", (color: { hexString: string }): void => {
            if (!this.activeElement) {
                return
            }
            this.activeElement.style.backgroundColor = color.hexString
        })
        this.picker.on("input:end", async (color: { hexString: string }): Promise<void> => {
            if (!this.activeProjectID) {
                return
            }
            await this.context.actionDispatcher.dispatch("Project", {
                objectID: this.activeProjectID,
                color: color.hexString,
            }, "PATCH")
        })
        document.addEventListener("pointerdown", this.onDocumentPointerDown)
        super.start()
    }

    public override refresh(): void {
        document.querySelectorAll<HTMLElement>(".color-picker").forEach((element): void => {
            if (element.dataset.colorPickerBound === "true") {
                return
            }
            element.dataset.colorPickerBound = "true"
            element.addEventListener("click", (event: MouseEvent): void => {
                event.preventDefault()
                event.stopPropagation()
                const projectID = element.dataset.project
                if (!projectID) {
                    return
                }
                this.activeElement = element
                this.activeProjectID = projectID
                this.picker.color.hexString = this.normalizeColor(getComputedStyle(element).backgroundColor)
                this.position(element)
                this.show()
            })
        })
    }

    private show(): void {
        this.panel.style.display = "block"
    }

    private hide(): void {
        this.panel.style.display = "none"
        this.activeElement = null
        this.activeProjectID = null
    }

    private position(anchor: HTMLElement): void {
        const rect = anchor.getBoundingClientRect()
        const panelWidth = 200
        const panelHeight = 230
        const gap = 8
        const maxLeft = window.innerWidth - panelWidth - 8
        const maxTop = window.innerHeight - panelHeight - 8
        const left = Math.max(8, Math.min(rect.left, maxLeft))
        const top = Math.max(8, Math.min(rect.bottom + gap, maxTop))
        this.panel.style.left = `${left}px`
        this.panel.style.top = `${top}px`
    }

    private normalizeColor(value: string): string {
        if (value.startsWith("#")) {
            return value
        }
        const match = value.match(/\d+/g)
        if (!match || match.length < 3) {
            return "#3b82f6"
        }
        const [r, g, b] = match.map(Number)
        return `#${this.toHex(r)}${this.toHex(g)}${this.toHex(b)}`
    }

    private toHex(value: number): string {
        return Math.max(0, Math.min(255, value)).toString(16).padStart(2, "0")
    }
}
