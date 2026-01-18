/**
 * About View - Functionality
 * Handles external links, tooltips, keyboard shortcuts, and Easter eggs
 */

document.addEventListener("DOMContentLoaded", () => {
    initializeTooltips();
    initializeKeyboardShortcuts();
    initializeEasterEgg();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle=\"tooltip\"]");
    [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
}

/**
 * Initialize keyboard shortcuts
 */
function initializeKeyboardShortcuts() {
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" || (e.key === "w" && (e.metaKey || e.ctrlKey))) {
            e.preventDefault()
            window.close()
        }
    });
}

/**
 * Easter egg - Click app icon 5 times
 */
function initializeEasterEgg() {
    const appIcon = document.querySelector(".app-icon")
    if (!appIcon) {
        return
    }
    let clickCount = 0
    let resetTimeout
    appIcon.addEventListener("click", () => {
        clickCount++
        appIcon.classList.add("shake")
        setTimeout(() => appIcon.classList.remove("shake"), 500)
        clearTimeout(resetTimeout)
        resetTimeout = setTimeout(() => {
            clickCount = 0
        }, 2000)
        if (clickCount === 5) {
            showEasterEgg()
            clickCount = 0
        }
    });
}

/**
 * Show Easter egg message
 */
function showEasterEgg() {
    const messages = [
        "🎉 Made with ❤️ and lots of ☕",
        "🚀 Powered by determination and caffeine",
        "✨ You found the secret! You are awesome!",
        "🎨 Design is not just what it looks like, design is how it works",
        "💻 Code is poetry in motion"
    ]
    const randomMessage = messages[Math.floor(Math.random() * messages.length)];
    showMessageBox("Alert", randomMessage, ["OK"])
}

/**
 * Copy version info to clipboard
 */
function copyVersionInfo() {
    const bundleName = document.querySelector("h2")?.textContent || ""
    const version = document.querySelector(".badge.bg-primary")?.textContent || ""
    const build = document.querySelector(".badge-secondary")?.textContent || ""
    const versionInfo = `${bundleName}\n${version}\n${build}`
    navigator.clipboard.writeText(versionInfo).then(() => {
        const btn = event.target.closest("button")
        if (btn) {
            const originalHTML = btn.innerHTML
            btn.innerHTML = "<i class=\"bi bi-check\"></i> Copied!"
            setTimeout(() => {
                btn.innerHTML = originalHTML
            }, 2000)
        }
    }).catch(err => {
        console.error("Failed to copy version info:", err)
    })
}

window.openExternal = openExternal
window.closeWindow = closeWindow
window.copyVersionInfo = copyVersionInfo
