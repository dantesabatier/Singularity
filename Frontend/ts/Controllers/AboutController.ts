import {ViewController} from "@/Application/ViewController"

export class AboutController extends ViewController {
    private clickCount = 0
    private resetTimeout: number | null = null
    private readonly onDocumentClick = (event: MouseEvent): void => {
        const target = event.target
        if (!(target instanceof Element) || !this.supportsCurrentView()) {
            return
        }
        const actionElement = target.closest<HTMLElement>("[data-about-action]")
        if (!actionElement) {
            return
        }
        const action = actionElement.dataset.aboutAction
        switch (action) {
            case "close":
                window.close()
                return
            case "openURL": {
                const url = actionElement.dataset.aboutUrl
                if (!url) {
                    return
                }
                void this.context.desktopBridge.openURL(url)
                return
            }
            case "copyVersionInfo":
                if (actionElement instanceof HTMLButtonElement) {
                    void this.copyVersionInfo(actionElement)
                }
                return
            default:
                return
        }
    }
    private readonly onDocumentKeyDown = (event: KeyboardEvent): void => {
        if (!this.supportsCurrentView()) {
            return
        }
        if (event.key === "Escape" || (event.key === "w" && (event.metaKey || event.ctrlKey))) {
            event.preventDefault()
            window.close()
        }
    }

    protected override supportsCurrentView(): boolean {
        return document.querySelector('#main[data-view="about"]') !== null
    }

    protected override setup(): void {
        document.addEventListener("click", this.onDocumentClick)
        document.addEventListener("keydown", this.onDocumentKeyDown)
        this.initializeTooltips()
        this.initializeEasterEgg()
    }

    protected override viewDidUpdate(): void {
        this.initializeTooltips()
        this.initializeEasterEgg()
    }

    private initializeTooltips(): void {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            new window.bootstrap.Tooltip(element)
        })
    }

    private initializeEasterEgg(): void {
        const appIcon = document.querySelector(".app-icon")
        if (!(appIcon instanceof HTMLElement)) {
            return
        }
        appIcon.onclick = () => {
            this.clickCount += 1
            appIcon.classList.remove("shake")
            appIcon.offsetHeight
            appIcon.classList.add("shake")
            window.setTimeout(() => {
                appIcon.classList.remove("shake")
            }, 500)
            if (this.resetTimeout !== null) {
                window.clearTimeout(this.resetTimeout)
            }
            this.resetTimeout = window.setTimeout(() => {
                this.clickCount = 0
            }, 2000)
            if (this.clickCount >= 5) {
                void this.showEasterEgg()
                this.clickCount = 0
            }
        }
    }

    private async showEasterEgg(): Promise<void> {
        const messages = [
            "Made with love and lots of coffee",
            "Powered by determination and caffeine",
            "You found the secret, you are awesome",
            "Design is how it works",
            "Code is poetry in motion",
        ]
        const randomMessage = messages[Math.floor(Math.random() * messages.length)]
        await this.context.desktopBridge.showMessageBox("Alert", randomMessage, ["OK"])
    }

    private async copyVersionInfo(button: HTMLButtonElement): Promise<void> {
        const bundleName = document.querySelector("h2")?.textContent ?? ""
        const version = document.querySelector(".badge.bg-primary")?.textContent ?? ""
        const build = document.querySelector(".badge-secondary")?.textContent ?? ""
        const value = `${bundleName}\n${version}\n${build}`
        try {
            await navigator.clipboard.writeText(value)
            const originalHTML = button.innerHTML
            button.innerHTML = "<i class=\"bi bi-check\"></i> Copied!"
            window.setTimeout(() => {
                button.innerHTML = originalHTML
            }, 2000)
        } catch (error) {
            await this.context.desktopBridge.showErrorBox(error)
        }
    }
}
