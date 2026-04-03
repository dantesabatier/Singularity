import {Feature} from "@/Application/Feature"
import hljs from "highlight.js/lib/core"

export class PredicateHighlighterFeature extends Feature {
    private static isLanguageRegistered = false

    public override refresh(): void {
        this.registerLanguage()
        document.querySelectorAll<HTMLTextAreaElement>("textarea.code-editor").forEach((textarea) => {
            if (textarea.dataset.highlighted === "true") {
                return
            }
            textarea.dataset.highlighted = "true"
            const wrapper = document.createElement("div")
            wrapper.className = "pred-highlight-wrapper"
            textarea.parentNode?.insertBefore(wrapper, textarea)
            wrapper.appendChild(textarea)
            const highlight = document.createElement("pre")
            const code = document.createElement("code")
            code.className = "language-predicate"
            highlight.className = "pred-highlight-layer"
            highlight.setAttribute("aria-hidden", "true")
            highlight.appendChild(code)
            wrapper.insertBefore(highlight, textarea)

            const syncStyles = (): void => {
                requestAnimationFrame(() => {
                    const styles = window.getComputedStyle(textarea)
                    highlight.style.padding = styles.padding
                    highlight.style.fontSize = styles.fontSize
                    highlight.style.lineHeight = styles.lineHeight
                    highlight.style.fontFamily = styles.fontFamily
                    highlight.style.letterSpacing = styles.letterSpacing
                    highlight.style.wordSpacing = styles.wordSpacing
                    highlight.style.borderRadius = styles.borderRadius
                })
            }
            const syncScroll = (): void => {
                highlight.scrollTop = textarea.scrollTop
                highlight.scrollLeft = textarea.scrollLeft
            }
            const update = (): void => {
                code.textContent = textarea.value
                delete code.dataset.highlighted
                hljs.highlightElement(code)
                syncScroll()
            }

            textarea.addEventListener("input", update)
            textarea.addEventListener("scroll", syncScroll)

            syncStyles()
            update()
            new ResizeObserver(() => {
                syncStyles()
                update()
            }).observe(textarea)
        })
    }

    private registerLanguage(): void {
        if (PredicateHighlighterFeature.isLanguageRegistered || hljs.getLanguage("predicate")) {
            PredicateHighlighterFeature.isLanguageRegistered = true
            return
        }
        hljs.registerLanguage("predicate", (language) => ({
            case_insensitive: true,
            contains: [
                language.QUOTE_STRING_MODE,
                language.APOS_STRING_MODE,
                {
                    className: "title.function",
                    match: /\b[A-Za-z_][A-Za-z0-9_]*(?=\s*:\s*\()/,
                },
                {
                    className: "variable",
                    match: /\$[A-Za-z_][A-Za-z0-9_]*/,
                },
                {
                    className: "symbol",
                    match: /%[@K]/,
                },
                {
                    className: "keyword",
                    match: /\b(AND|OR|NOT|IN|BETWEEN|ANY|ALL|NONE|SOME|CONTAINS|BEGINSWITH|ENDSWITH|LIKE|MATCHES|TRUEPREDICATE|FALSEPREDICATE|SELF|SUBQUERY|CAST|FIRST|LAST|SIZE)\b/,
                },
                {
                    className: "literal",
                    match: /\b(true|false|YES|NO|NULL|NIL|null|nil)\b/,
                },
                language.C_NUMBER_MODE,
                {
                    className: "operator",
                    match: /==|!=|>=|<=|<>|>|<|=|\|\||&&|!/,
                },
            ],
        }))
        PredicateHighlighterFeature.isLanguageRegistered = true
    }
}
