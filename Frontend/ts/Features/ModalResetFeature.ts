import {Feature} from "@/Application/Feature"

export class ModalResetFeature extends Feature {
    private readonly onHiddenModal = (event: Event): void => {
        const modal = event.currentTarget
        if (!(modal instanceof HTMLElement)) {
            return
        }
        const form = modal.querySelector<HTMLFormElement>(".modal-content form")
        form?.reset()
    }

    public override start(): void {
        document.querySelectorAll<HTMLElement>(".modal").forEach(modal => modal.addEventListener("hidden.bs.modal", this.onHiddenModal))
    }
}
