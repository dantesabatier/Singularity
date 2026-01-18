/**
 * Welcome View - Functionality
 * Handles project creation and opening
 */

const showNewProjectView = () => {
    const welcomeView = document.getElementById("welcome-view")
    const newProjectView = document.getElementById("new-project-view")

    document.body.classList.add("in-new-project")
    welcomeView.classList.add("sliding-left")

    setTimeout(() => {
        welcomeView.classList.remove("active")
        newProjectView.classList.add("active")
        welcomeView.classList.remove("sliding-left")
    }, 300)
}

const showWelcomeView = () => {
    const welcomeView = document.getElementById("welcome-view")
    const newProjectView = document.getElementById("new-project-view")

    document.body.classList.remove("in-new-project")
    newProjectView.classList.add("sliding-right")

    setTimeout(() => {
        newProjectView.classList.remove("active")
        welcomeView.classList.add("active")
        newProjectView.classList.remove("sliding-right")
        /**
         * @type { HTMLFormElement }
         */
        const form = document.getElementById("new-project-form")
        form.reset()
        document.getElementById("directory").value = ""
        document.getElementById("create-project-btn").disabled = true
    }, 300)
}

const chooseProjectFolder = async () => {
    const directory = await browse()
    if (directory) {
        const pathInput = document.getElementById("directory")
        pathInput.value = directory
        const createBtn = document.getElementById("create-project-btn")
        createBtn.disabled = !pathInput.value.trim()
    }
}

const openProject = async () => {
    const directory = await browse()
    if (directory) {
        await send("Open", {
            directory
        })
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("new-project-form")
    if (form) {
        form.addEventListener("submit", async (e) => {
            e.preventDefault()
            showWelcomeView()
            await submit(form)
        })
    }
})
