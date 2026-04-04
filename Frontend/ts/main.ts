import "../scss/main.scss"
import * as bootstrap from "bootstrap"
window.bootstrap = bootstrap

const initializeApp = async (): Promise<void> => {
    if (document.getElementById("split-container")) {
        const module = await import("@/Bootstraps/editor")
        module.bootstrapEditor()
        return
    }
    if (document.querySelector("db-viewer")) {
        const module = await import("@/Bootstraps/viewer")
        module.bootstrapViewer()
        return
    }
    if (document.getElementById("welcome-view")) {
        const module = await import("@/Bootstraps/welcome")
        module.bootstrapWelcome()
        return
    }
    if (document.querySelector('#main[data-view="about"]')) {
        const module = await import("@/Bootstraps/about")
        module.bootstrapAbout()
        return
    }
    if (document.querySelector('#main[data-view="preferences"]')) {
        const module = await import("@/Bootstraps/preferences")
        module.bootstrapPreferences()
        return
    }
    const module = await import("@/Bootstraps/default")
    module.bootstrapDefault()
}

void initializeApp()
