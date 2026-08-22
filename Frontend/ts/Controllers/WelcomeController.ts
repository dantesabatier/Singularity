import {ViewController} from "@/Application/ViewController"

export class WelcomeController extends ViewController {
    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const actionElement = target.closest<HTMLElement>("[data-welcome-action]")
        if (!actionElement || !this.supportsCurrentView()) {
            return
        }
        const action = actionElement.dataset.welcomeAction
        switch (action) {
            case "showNewProjectView":
                this.showNewProjectView()
                return
            case "showWelcomeView":
                this.showWelcomeView()
                return
            case "chooseProjectFolder":
                void this.chooseProjectFolder()
                return
            case "openProject":
                void this.openProject()
                return
            case "openEditor":
                void this.openEditor(actionElement)
                return
            case "renameProject":
                this.renameProject(actionElement)
                return
            case "openPath":
                void this.openPath(actionElement)
                return
            case "removeProject":
                void this.removeProject(actionElement)
                return
            case "showPreferences":
                void this.context.desktopBridge.showPreferences()
                return
            case "showAboutPanel":
                void this.context.desktopBridge.showAboutPanel()
                return
            case "showHelp":
                void this.context.desktopBridge.showHelp()
                return
            default:
                return
        }
    }
    private readonly onFormSubmit = async (event: SubmitEvent): Promise<void> => {
        const form = event.target
        if (!(form instanceof HTMLFormElement) || form.id !== "new-project-form") {
            return
        }
        event.preventDefault()
        this.showWelcomeView()
        await this.context.formSubmissionService.submit(form)
    }
    private readonly onModalShown = (event: Event): void => {
        const modal = event.target
        if (!(modal instanceof HTMLElement) || modal.id !== "editProject") {
            return
        }
        const nameInput = modal.querySelector<HTMLInputElement>('input[name="name"]')
        nameInput?.focus()
    }

    protected override setup(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("submit", this.onFormSubmit)
        document.addEventListener("shown.bs.modal", this.onModalShown)
    }

    protected override supportsCurrentView(): boolean {
        return document.getElementById("welcome-view") !== null
    }

    private showNewProjectView(): void {
        const welcomeView = document.getElementById("welcome-view")
        const newProjectView = document.getElementById("new-project-view")
        if (!welcomeView || !newProjectView) {
            return
        }
        document.body.classList.add("in-new-project")
        welcomeView.classList.add("sliding-left")
        setTimeout(() => {
            welcomeView.classList.remove("active")
            newProjectView.classList.add("active")
            welcomeView.classList.remove("sliding-left")
        }, 300)
    }

    private showWelcomeView(): void {
        const welcomeView = document.getElementById("welcome-view")
        const newProjectView = document.getElementById("new-project-view")
        if (!welcomeView || !newProjectView) {
            return
        }
        document.body.classList.remove("in-new-project")
        newProjectView.classList.add("sliding-right")
        setTimeout(() => {
            newProjectView.classList.remove("active")
            welcomeView.classList.add("active")
            newProjectView.classList.remove("sliding-right")
            const form = document.getElementById("new-project-form")
            if (!(form instanceof HTMLFormElement)) {
                return
            }
            form.reset()
            const directoryInput = document.getElementById("directory")
            if (directoryInput instanceof HTMLInputElement) {
                directoryInput.value = ""
            }
            const createButton = document.getElementById("create-project-btn")
            if (createButton instanceof HTMLButtonElement) {
                createButton.disabled = true
            }
        }, 300)
    }

    private async chooseProjectFolder(): Promise<void> {
        const directory = await this.context.desktopBridge.browse()
        if (!directory) {
            return
        }
        const pathInput = document.getElementById("directory")
        if (!(pathInput instanceof HTMLInputElement)) {
            return
        }
        pathInput.value = directory
        const createButton = document.getElementById("create-project-btn")
        if (createButton instanceof HTMLButtonElement) {
            createButton.disabled = pathInput.value.trim().length === 0
        }
    }

    private async openProject(): Promise<void> {
        const directory = await this.context.desktopBridge.browse()
        if (!directory) {
            return
        }
        await this.context.actionDispatcher.dispatch("Open", {directory})
    }

    private async openEditor(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        if (!projectID) {
            return
        }
        const url = `${window.location.origin}${this.context.routeBuilder.build("Editor", {project: projectID})}`
        await this.context.desktopBridge.showWindow({
            url,
            overrideBrowserWindowOptions: {
                width: 1280,
                height: 760,
            },
        })
    }

    private renameProject(element: HTMLElement): void {
        const modalElement = document.getElementById("editProject")
        if (!(modalElement instanceof HTMLElement)) {
            return
        }
        const form = modalElement.querySelector<HTMLFormElement>(".modal-content form")
        if (!form) {
            return
        }
        const nameInput = form.querySelector<HTMLInputElement>('input[name="name"]')
        if (nameInput) {
            nameInput.value = element.dataset.projectName ?? ""
        }
        const projectID = form.querySelector<HTMLInputElement>('input[name="objectID"]')
        if (projectID) {
            projectID.value = element.dataset.projectId ?? ""
        }
        const modal = new window.bootstrap.Modal(modalElement)
        modal.show()
    }

    private async openPath(element: HTMLElement): Promise<void> {
        const path = element.dataset.projectPath
        if (!path) {
            return
        }
        await this.context.desktopBridge.openPath(path)
    }

    private async removeProject(element: HTMLElement): Promise<void> {
        const projectID = element.dataset.projectId
        const projectName = element.dataset.projectName ?? "Project"
        if (!projectID) {
            return
        }
        const result = await this.context.desktopBridge.showMessageBox(`Remove "${projectName}"?`, "This action cannot be undone.", ["Cancel", "OK"])
        if (!result?.response) {
            return
        }
        await this.context.actionDispatcher.dispatch("Project", {objectID: projectID}, "DELETE")
    }
}
