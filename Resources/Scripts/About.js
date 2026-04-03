/**
 * About View - Functionality
 * Handles external links, tooltips, keyboard shortcuts, and Easter eggs
 */

document.addEventListener("DOMContentLoaded", () => {
    initializeTooltips()
    initializeKeyboardShortcuts()
    initializeEasterEgg()
    initializeActions()
})

/**
 * Initialize Bootstrap tooltips
 */
const initializeTooltips = () => document.querySelectorAll("[data-bs-toggle=\"tooltip\"]").forEach(e => new bootstrap.Tooltip(e))

/**
 * Initialize keyboard shortcuts
 */
const initializeKeyboardShortcuts = () => {
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" || (e.key === "w" && (e.metaKey || e.ctrlKey))) {
            e.preventDefault()
            window.close()
        }
    })
}

/**
 * Easter egg - Click app icon 5 times
 */
const initializeEasterEgg = () => {
    const appIcon = document.querySelector(".app-icon")
    if (!appIcon) {
        return
    }
    let clickCount = 0
    let resetTimeout = null
    appIcon.addEventListener("click", () => {
        clickCount++
        appIcon.classList.remove("shake")
        appIcon.offsetHeight
        appIcon.classList.add("shake")
        setTimeout(() => {
            appIcon.classList.remove("shake")
        }, 500)
        clearTimeout(resetTimeout)
        resetTimeout = setTimeout(() => {
            clickCount = 0
        }, 2000)
        if (clickCount >= 5) {
            showEasterEgg()
            clickCount = 0
        }
    })
}

const initializeActions = () => {
    document.addEventListener("click", (event) => {
        const target = event.target
        if (!(target instanceof Element)) {
            return
        }
        const button = target.closest("[data-about-action]")
        if (!button) {
            return
        }
        const action = button.getAttribute("data-about-action")
        switch (action) {
            case "close":
                window.close()
                return
            case "openURL": {
                const url = button.getAttribute("data-about-url")
                if (!url) {
                    return
                }
                window.api?.openURL(url)
                return
            }
            case "copyVersionInfo":
                if (button instanceof HTMLButtonElement) {
                    copyVersionInfo(button)
                }
                return
            default:
                return
        }
    })
}

/**
 * Show Easter egg message
 */
const showEasterEgg = () => {
    const messages = [
        "🎉 Made with ❤️ and lots of ☕",
        "🚀 Powered by determination and caffeine",
        "✨ You found the secret! You are awesome!",
        "🎨 Design is not just what it looks like, design is how it works",
        "💻 Code is poetry in motion"
    ]
    const randomMessage = messages[Math.floor(Math.random() * messages.length)]
    window.api?.showMessageBox("Alert", randomMessage, ["OK"])
}

/**
 * Copy version info to clipboard
 * @param {HTMLButtonElement} sender
 */
const copyVersionInfo = sender => {
    const bundleName = document.querySelector("h2")?.textContent || ""
    const version = document.querySelector(".badge.bg-primary")?.textContent || ""
    const build = document.querySelector(".badge-secondary")?.textContent || ""
    const versionInfo = `${bundleName}\n${version}\n${build}`
    navigator.clipboard.writeText(versionInfo).then(() => {
        const originalHTML = sender.innerHTML
        sender.innerHTML = "<i class=\"bi bi-check\"></i> Copied!"
        setTimeout(() => {
            sender.innerHTML = originalHTML
        }, 2000)
    }).catch(err => {
        console.error("Failed to copy version info:", err)
    })
}
