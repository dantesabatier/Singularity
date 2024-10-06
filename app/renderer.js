// noinspection JSUnusedGlobalSymbols,JSUnresolvedReference

const url = (endpoint, parameters) => {
    let url = endpoint
    if (!url.startsWith("/")) {
        url = "/" + url
    }
    if (!!parameters && JSON.stringify(parameters) !== JSON.stringify({})) {
        url += "?" + (() => {
            const array = []
            for (const k in parameters) {
                if (parameters.hasOwnProperty(k)) {
                    array.push(encodeURIComponent(k) + "=" + encodeURIComponent(parameters[k]))
                }
            }
            return array.join("&")
        })()
    }
    return url
}

/**
 * @param {string} url
 * @param {function|undefined} completion
 */
const replace = async (url, completion = undefined) => {
    const headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    const response = await fetch(url, {
        method: "GET",
        headers: headers
    })
    if (!response.ok) {
        await showErrorBox((await response.json())?.error)
        return
    }
    const data = await response.text()
    const doc = document.implementation.createHTMLDocument()
    doc.open()
    doc.write(data)
    doc.close()
    const e2 = doc.getElementById("main")
    const info = Array.from(document.querySelectorAll(`[class*="scroll-view"]`)).reduce((obj, e) => {
        obj[e.id] = e.scrollTop
        return obj
    }, {})
    const e1 = document.getElementById("main")
    e1.innerHTML = e2.innerHTML
    document.querySelectorAll(`[class*="scroll-view"]`).forEach(e => e.scroll(0, info[e.id]))
    const body = document.body
    body.removeAttribute("class")
    body.removeAttribute("style")
    const backdrop = document.querySelector(".modal-backdrop")
    if (backdrop) {
        backdrop.remove()
    }
    if (completion) {
        completion()
    }
}

/**
 * @param {string} url
 * @param {function|undefined} completion
 */
const push = async (url, completion = undefined) => {
    await replace(url, completion)
    await history.pushState({url: url}, "", url)
}

/**
 * @param {string} action
 * @param {object|undefined} body
 * @param {string} method
 * @param {function|undefined} completion
 */
const send = async (action, body = undefined, method = "POST", completion = undefined) => {
    const location = new URL(window.location)
    setProgressBar(1.1)
    const headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    headers["Content-Type"] = "application/json; charset=utf-8"
    const response = await fetch(action, {
        method: method,
        headers: headers,
        body: !!body ? JSON.stringify(body) : undefined
    })
    if (!response.ok) {
        await showErrorBox((await response.json())?.error)
        await replace(location.href, completion)
        setProgressBar(-1)
        return
    }
    const keys = []
    const endpoint = URL.canParse(action) ? new URL(action).pathname.replace("/", "") : action.replace("/", "")
    const m = method.toUpperCase()
    switch (m) {
        case "POST":
        case "DELETE":
            switch (endpoint) {
                case "Entity":
                case "FetchRequestTemplate":
                case "Configuration":
                    keys.push("project")
                    break
                case "Attribute":
                case "Relationship":
                case "FetchedProperty":
                case "FetchIndex":
                case "UniquenessConstraint":
                    keys.push(...["project", "entity"])
                    break
                case "FetchIndexElement":
                    keys.push(...["project", "entity", "index"])
                    break
            }
            const values = keys.map(k => `${k}=${location.searchParams.get(k)}`)
            if (m === "POST") {
                const objectID = (await response.json())?.objectID
                if (objectID) {
                    const entity = (() => {
                        switch (endpoint) {
                            case "Entity":
                                return "entity"
                            case "FetchRequestTemplate":
                                return "fetchRequest"
                            case "Configuration":
                                return "configuration"
                            case "Attribute":
                            case "Relationship":
                            case "FetchedProperty":
                                return "property"
                            case "FetchIndex":
                                return "index"
                            case "UniquenessConstraint":
                                return "constraint"
                            case "FetchIndexElement":
                                return "element"
                            default:
                                return undefined
                        }
                    })()
                    if (entity) {
                        values.push(`${entity}=${objectID}`)
                    }
                }
            }
            if (!!values.length) {
                location.search = values.join("&")
            }
            break
    }
    await push(location.href, completion)
    setProgressBar(-1)
}

/**
 *
 * @param {HTMLFormElement} form
 * @returns {{method: string, body: object}}
 */
const parse = (form) => {
    const elements = Array.from(form.elements)
    return {
        body: elements.filter(e => !!e.name && e.type !== "submit" && e.name !== "X-Http-Method-Override").reduce((result, e) => {
            result[e.name] = (() => {
                const value = e.type === "checkbox" ? e.checked : e.value
                if (value === "true") {
                    return true
                } else if (value === "false") {
                    return false
                } else if (value === "" || value === "null") {
                    return null
                } else if (!isNaN(value) && !isNaN(parseFloat(value))) {
                    return parseFloat(value)
                }
                return value
            })()
            return result
        }, {}),
        method: elements.find(e => e.name === "X-Http-Method-Override")?.value ?? form.method ?? "POST"
    }
}

/**
 * @param {HTMLFormElement} form
 * @param {function|undefined} completion
 */
const submit = async (form, completion = undefined) => {
    const headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    headers["Content-Type"] = "application/json; charset=utf-8"
    const parsed = parse(form)
    await send(form.action, parsed.body, parsed.method, completion)
}

/**
 * @param {object} project
 */
const show = async (project) => window.api?.showWindow({
    url: `${window.location.origin}${url("Editor", {project: project.objectID})}`,
    overrideBrowserWindowOptions: {
        width: 1090,
        height: 600
    }
})

/**
 * @param {object} project
 * @param {function|undefined} completion
 */
const save = async (project, completion = undefined) => await send(url("Save"), {project: project.objectID}, "POST", completion)

/**
 * @param {object} project
 * @param {function|undefined} completion
 */
const subclass = async (project, completion = undefined) => await send(url("Subclass"), {project: project.objectID}, "POST", completion)

/**
 * @param {object} project
 * @param {string|undefined} path
 * @param {function|undefined} completion
 */
const model = async (project, path, completion = undefined) => {
    const filePath = (await window.api.showOpenDialog("Import Model", "Select the model file", "Import", path, ["openFile"], [
        {
            name: "Property list",
            extensions: ["plist"]
        }
    ])).filePaths.find(Boolean)
    if (filePath) {
        await send(url("import"), {project: project.objectID, path: filePath}, "POST", completion)
    }
}

/**
 * @param {function|undefined} completion
 */
const create = async (completion = undefined) => {
    const filePath = (await window.api.showOpenDialog("New Project", "Select or create a folder", "Create", undefined, ["openDirectory", "promptToCreate"])).filePaths.find(Boolean)
    if (filePath) {
        await send(url("create"), {path: filePath}, "POST", completion)
    }
}

/**
 * @param {string} entity
 * @param {string} name
 * @param {object} parent
 * @param {function|undefined} completion
 */
const add = async (entity, name, parent, completion = undefined) => {
    switch (entity) {
        case "Entity":
        case "FetchRequestTemplate":
        case "Configuration":
            await send(url(entity), {
                name: name,
                modelID: parent.objectID
            }, "POST", completion)
            break
        case "Attribute":
        case "Relationship":
        case "FetchedProperty":
        case "FetchIndex":
            await send(url(entity), {
                name: name,
                entityPropertyID: parent.objectID
            }, "POST", completion)
            break
        case "FetchIndexElement":
            await send(url(entity), {
                propertyName: name,
                indexID: parent.objectID
            }, "POST", completion)
            break
        default:
            break
    }
}

/**
 * @param {object} item
 * @param completion
 */
const remove = async (item, completion = undefined) => {
    if ((await window.api?.showMessageBox(`Remove "${item.name ?? item.propertyName ?? item.stringValue}"?`, "This action cannot be undone.", ["Cancel", "OK"]))?.response) {
        await send(url(item.entityName), {objectID: item.objectID}, "DELETE", completion)
    }
}

const explorer = (path) => window.api?.openPath(path)

/**
 * @param {string} messageText
 * @param {string} informativeText
 * @param {string[]} buttons
 */
const showMessageBox = (messageText, informativeText, buttons) => window.api?.showMessageBox(messageText, informativeText, buttons)

/**
 * @param {object|undefined} error
 */
const showErrorBox = (error) => window.api?.showErrorBox(error)

const showPreferences = () => window.api?.showWindow({
    url: `${window.location.origin}${url("Preferences")}`,
    overrideBrowserWindowOptions: {
        width: 600,
        height: 400,
        modal: true
    }
})

const showAboutPanel = () => window.api?.showWindow({
    url: `${window.location.origin}${url("About")}`,
    overrideBrowserWindowOptions: {
        width: 380,
        height: 220,
        modal: true,
        titleBarOverlay: false
    }
})

/**
 * @param {number} progress
 */
const setProgressBar = (progress) => window.api?.setProgressBar(progress)
