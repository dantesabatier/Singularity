// noinspection JSUnresolvedReference

const initPredicateHighlighting = () => {
    if (!hljs.getLanguage("predicate")) {
        hljs.registerLanguage("predicate", hljs => ({
            case_insensitive: true,
            contains: [
                hljs.QUOTE_STRING_MODE,
                hljs.APOS_STRING_MODE,
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
                hljs.C_NUMBER_MODE,
                {
                    className: "operator",
                    match: /==|!=|>=|<=|<>|>|<|=|\|\||&&|!/,
                },
            ],
        }))
    }

    document.querySelectorAll("textarea.code-editor").forEach(textarea => {
        if (textarea.dataset.highlighted) {
            return
        }
        textarea.dataset.highlighted = "true"

        const wrapper = document.createElement("div")
        wrapper.className = "pred-highlight-wrapper"
        textarea.parentNode.insertBefore(wrapper, textarea)
        wrapper.appendChild(textarea)

        const highlight = document.createElement("pre")
        const code = document.createElement("code")
        code.className = "language-predicate"
        highlight.className = "pred-highlight-layer"
        highlight.setAttribute("aria-hidden", "true")
        highlight.appendChild(code)
        wrapper.insertBefore(highlight, textarea)
        const syncStyles = () => {
            requestAnimationFrame(() => {
                const cs = window.getComputedStyle(textarea)
                highlight.style.padding       = cs.padding
                highlight.style.fontSize      = cs.fontSize
                highlight.style.lineHeight    = cs.lineHeight
                highlight.style.fontFamily    = cs.fontFamily
                highlight.style.letterSpacing = cs.letterSpacing
                highlight.style.wordSpacing   = cs.wordSpacing
                highlight.style.borderRadius  = cs.borderRadius
            })
        }

        const syncScroll = () => {
            highlight.scrollTop = textarea.scrollTop
            highlight.scrollLeft = textarea.scrollLeft
        }

        const update = () => {
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
            syncStyles();
            update()
        }).observe(textarea)
    })
}

document.addEventListener("DOMContentLoaded", initPredicateHighlighting, {once: true})
document.addEventListener("view:updated", () => {
    document.querySelectorAll("textarea.code-editor").forEach(t => delete t.dataset.highlighted)
    initPredicateHighlighting()
})
