/**
 * Preferences View - Active Navigation Handler
 * Updates active state in sidebar navigation based on scroll position
 */

document.addEventListener("DOMContentLoaded", () => {
    const sections = document.querySelectorAll('#main section[id]');
    const navItems = document.querySelectorAll('#main .nav-item');
    if (sections.length === 0 || navItems.length === 0) {
        return
    }
    const observerOptions = {
        root: null,
        rootMargin: "-20% 0px -70% 0px",
        threshold: 0
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const sectionId = entry.target.getAttribute("id")
                updateActiveNavItem(sectionId)
            }
        });
    }, observerOptions)
    sections.forEach(section => {
        observer.observe(section)
    })
    navItems.forEach(item => {
        item.addEventListener("click", (e) => {
            const href = item.getAttribute("href")
            if (href && href.startsWith("#")) {
                e.preventDefault()
                const sectionId = href.substring(1)
                const section = document.getElementById(sectionId)
                if (section) {
                    section.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    })
                    updateActiveNavItem(sectionId)
                    history.pushState(null, null, href)
                }
            }
        })
    })

    function updateActiveNavItem(sectionId) {
        navItems.forEach(item => {
            const href = item.getAttribute("href")
            if (href === `#${sectionId}`) {
                item.classList.add("active")
            } else {
                item.classList.remove("active")
            }
        })
    }

    if (window.location.hash) {
        const initialSection = window.location.hash.substring(1)
        const section = document.getElementById(initialSection)
        if (section) {
            setTimeout(() => {
                section.scrollIntoView({behavior: "smooth"})
                updateActiveNavItem(initialSection)
            }, 100);
        }
    }
})
