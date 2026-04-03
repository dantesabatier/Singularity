(() => {
    const runtime = document.getElementById("editor-runtime")
    const source = document.getElementById("source")
    const content = document.getElementById("content")
    const sidebar = document.getElementById("sidebar")
    if (!runtime || !source || !content || !sidebar) {
        return
    }
    const projectObjectID = runtime.dataset.projectObjectId ?? ""
    const raw = localStorage.getItem(`editorSplitSizes:${projectObjectID}`)
    if (!raw) {
        return
    }
    try {
        const parsed = JSON.parse(raw)
        if (!Array.isArray(parsed) || parsed.length !== 3) {
            return
        }
        source.style.width = `${Number(parsed[0])}%`
        content.style.width = `${Number(parsed[1])}%`
        sidebar.style.width = `${Number(parsed[2])}%`
    } catch {
    }
})()
