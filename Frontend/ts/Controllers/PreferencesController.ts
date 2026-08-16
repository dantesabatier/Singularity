import {ViewController} from "@/Application/ViewController"

export class PreferencesController extends ViewController {
    private observer: IntersectionObserver | null = null
    private activeSectionID: string = "general"

    protected override supportsCurrentView(): boolean {
        return document.querySelector('#main[data-view="preferences"]') !== null
    }

    protected override setup(): void {
        this.activeSectionID = window.location.hash.slice(1) || "general"
        this.bindNavigation(true)
    }

    protected override viewDidUpdate(): void {
        this.activeSectionID = window.location.hash.slice(1) || this.activeSectionID || "general"
        this.bindNavigation(false)
    }

    private bindNavigation(scrollToHash = false): void {
        this.observer?.disconnect()
        const sections = Array.from(document.querySelectorAll<HTMLElement>('#main[data-view="preferences"] section[id]'))
        const navItems = Array.from(document.querySelectorAll<HTMLAnchorElement>('#main[data-view="preferences"] .nav-item'))
        if (sections.length === 0 || navItems.length === 0) {
            return
        }
        this.updateActiveNavItem(navItems, this.activeSectionID)

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return
                }
                this.activeSectionID = entry.target.id
                this.updateActiveNavItem(navItems, entry.target.id)
            })
        }, {
            root: null,
            rootMargin: "-20% 0px -70% 0px",
            threshold: 0,
        })
        sections.forEach((section) => this.observer?.observe(section))
        navItems.forEach((item) => {
            item.onclick = (event) => {
                const href = item.getAttribute("href")
                if (!href || !href.startsWith("#")) {
                    return
                }
                event.preventDefault()
                const sectionID = href.slice(1)
                const section = document.getElementById(sectionID)
                if (!section) {
                    return
                }
                this.activeSectionID = sectionID
                this.updateActiveNavItem(navItems, sectionID)
                section.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                })
                history.pushState(null, "", href)
            }
        })
        if (!scrollToHash || !window.location.hash) {
            return
        }
        const initialSectionID = window.location.hash.slice(1)
        const section = document.getElementById(initialSectionID)
        if (!section) {
            return
        }
        section.scrollIntoView({behavior: "smooth"})
        this.activeSectionID = initialSectionID
        this.updateActiveNavItem(navItems, initialSectionID)
    }

    private updateActiveNavItem(navItems: HTMLAnchorElement[], sectionID: string): void {
        navItems.forEach((item) => {
            item.classList.toggle("active", item.getAttribute("href") === `#${sectionID}`)
        })
    }
}
