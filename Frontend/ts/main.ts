import "../scss/main.scss"
import * as bootstrap from "bootstrap"
window.bootstrap = bootstrap

const initializeApp = async (): Promise<void> => {
    const currentView = document.querySelector("#main")?.getAttribute("data-view")
    switch (currentView) {
        case "editor": {
            const module = await import("@/Bootstraps/editor")
            module.bootstrapEditor()
            return
        }
        case "mapping": {
            const module = await import("@/Bootstraps/mapping")
            module.bootstrapMapping()
            return
        }
        case "viewer": {
            const module = await import("@/Bootstraps/viewer")
            module.bootstrapViewer()
            return
        }
        case "welcome": {
            const module = await import("@/Bootstraps/welcome")
            module.bootstrapWelcome()
            return
        }
        case "about": {
            const module = await import("@/Bootstraps/about")
            module.bootstrapAbout()
            return
        }
        case "preferences": {
            const module = await import("@/Bootstraps/preferences")
            module.bootstrapPreferences()
            return
        }
        case "help": {
            const module = await import("@/Bootstraps/help")
            module.bootstrapHelp()
            return
        }
        default:
            break
    }
    const module = await import("@/Bootstraps/default")
    module.bootstrapDefault()
}

void initializeApp()
