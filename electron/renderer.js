// noinspection JSUnusedGlobalSymbols,JSUnresolvedReference

/**
 * @param {string} endpoint
 * @param {Object|undefined} parameters
 * @return {string}
 */
const url = (endpoint, parameters = undefined) => {
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
 * @param {Object|undefined} body
 * @param {string} method
 */
const request = async (url, body = undefined, method = "POST") => {
    const headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    if (body) {
        headers["Content-Type"] = "application/json; charset=utf-8"
    }
    return await fetch(url, {
        method: method,
        headers: headers,
        body: !!body ? JSON.stringify(body) : undefined
    })
}

/**
 * @param {string} url
 */
const replace = async (url) => {
    const response = await request(url, undefined, "GET")
    if (!response.ok) {
        await showErrorBox((await response.json())?.error)
        return
    }
    const info = Array.from(document.querySelectorAll(`[class*="scroll-view"]`)).reduce((obj, e) => {
        obj[e.id] = e.scrollTop
        return obj
    }, {})
    const html = await response.text()
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, "text/html")
    const e2 = doc.getElementById("main")
    const e1 = document.getElementById("main")
    if (e1 && e2) {
        e1.innerHTML = e2.innerHTML
    }
    document.querySelectorAll(`[class*="scroll-view"]`).forEach(e => e.scroll(0, info[e.id]))
    const body = document.body
    body.removeAttribute("class")
    body.removeAttribute("style")
    const backdrop = document.querySelector(".modal-backdrop")
    if (backdrop) {
        backdrop.remove()
    }
    document.dispatchEvent(new CustomEvent("view:updated", {
        detail: {url: url}
    }))
}

/**
 * @param {string} url
 */
const push = async (url) => {
    await replace(url)
    if (window.location.href === url) {
        return
    }
    history.pushState({url: url}, "", url)
}

/**
 * @param {string} action
 * @param {Object|undefined} body
 * @param {string} method
 */
const send = async (action, body = undefined, method = "POST") => {
    const location = new URL(window.location ?? "/")
    setProgressBar(1.1)
    const response = await request(action, body, method)
    if (!response.ok) {
        await showErrorBox((await response.json())?.error)
        await replace(location.href)
        setProgressBar(-1)
        return
    }
    const keys = []
    const url = URL.canParse(action) ? new URL(action) : undefined
    const endpoint = url?.pathname.replace("/", "") ?? action.replace("/", "")
    const m = method.toUpperCase()
    switch (m) {
        case "POST":
        case "DELETE":
            switch (endpoint) {
                case "Entity":
                case "FetchRequestTemplate":
                case "Configuration":
                case "CompositeType":
                    keys.push("project")
                    break
                case "Attribute":
                    keys.push("project")
                    if (location.searchParams.get("entity")) {
                        keys.push("entity")
                    } else if (location.searchParams.get("composite")) {
                        keys.push("composite")
                    }
                    break
                case "Relationship":
                case "FetchedProperty":
                case "FetchIndex":
                case "UniquenessConstraint":
                    keys.push(...["project", "entity"])
                    break
                case "FetchIndexElement":
                    keys.push(...["project", "entity", "index"])
                    break
                case "AccessControl":
                    keys.push(...["project", "entity", "property"])
                    break
                default:
                    break
            }
            const values = keys.map(k => `${k}=${location.searchParams.get(k)}`)
            if (m === "POST") {
                const objectID = (() => {
                    switch (response.status) {
                        case 200:
                        case 201:
                            return true
                        default:
                            return false
                    }
                })() ? (await response.json())?.objectID : false
                if (objectID) {
                    const entity = (() => {
                        switch (endpoint) {
                            case "Entity":
                                return "entity"
                            case "FetchRequestTemplate":
                                return "fetchRequest"
                            case "Configuration":
                                return "configuration"
                            case "CompositeType":
                                return "composite"
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
                            case "AccessControl":
                                return "accessControl"
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
        default:
            break
    }
    await push(location.href)
    setProgressBar(-1)
}

/**
 *
 * @param {HTMLFormElement} form
 * @returns {{method: string, body: Object}}
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
 */
const submit = async (form) => {
    const headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    headers["Content-Type"] = "application/json; charset=utf-8"
    const parsed = parse(form)
    await send(form.action, parsed.body, parsed.method)
}

/**
 * @param {Object} project
 */
const show = async (project) => window.api?.showWindow({
    url: `${window.location.origin}${url("Editor", {project: project.objectID})}`,
    overrideBrowserWindowOptions: {
        width: 1090,
        height: 600
    }
})

const view = async (project) => window.api?.showWindow({
    url: `${window.location.origin}${url("Viewer", {project: project.objectID})}`,
    overrideBrowserWindowOptions: {
        width: 1090,
        height: 600
    }
})

/**
 * @param {Object} project
 */
const save = async (project) => await send(url("Save"), {project: project.objectID}, "POST")

/**
 * @param {Object} project
 */
const subclass = async (project) => await send(url("Subclass"), {project: project.objectID}, "POST")

/**
 * @param {Object} project
 * @param {string|undefined} path
 */
const model = async (project, path) => {
    const filePath = (await window.api?.showOpenDialog("Import Model", "Select the model file", "Import", path, ["openFile"], [
        {
            name: "Property list",
            extensions: ["plist"]
        }
    ])).filePaths.find(Boolean)
    if (filePath) {
        await send(url("import"), {project: project.objectID, path: filePath}, "POST")
    }
}

/**
 * @param {string} entity
 * @param {string} name
 * @param {Object} parent
 * @param {number} position
 */
const add = async (entity, name, parent, position) => {
    switch (entity) {
        case "Entity":
        case "FetchRequestTemplate":
        case "Configuration":
        case "CompositeType":
            await send(url(entity), {
                name: name,
                model: parent
            }, "POST")
            break
        case "Attribute":
            const obj = {
                name: name,
                position: position
            }
            if (parent.entityName === "Entity") {
                obj.entityProperty = parent
            } else if (parent.entityName === "CompositeType") {
                obj.compositeType = parent
            }
            await send(url(entity), obj, "POST")
            break
        case "Relationship":
        case "FetchedProperty":
            await send(url(entity), {
                name: name,
                position: position,
                entityProperty: parent
            }, "POST")
            break
        case "FetchIndex":
            await send(url(entity), {
                name: name,
                entityProperty: parent
            }, "POST")
            break
        case "FetchIndexElement":
            await send(url(entity), {
                propertyName: name,
                index: parent
            }, "POST")
            break
        case "AccessControl":
            await send(url(entity), {
                name: name,
                property: parent
            }, "POST")
            break
        case "Role":
            await send(url(entity), {
                name: name,
                accessControl: parent
            }, "POST")
            break
        default:
            break
    }
}

/**
 * @param {Object} item
 */
const remove = async (item) => {
    if ((await window.api?.showMessageBox(`Remove "${item.name ?? item.propertyName ?? item.stringValue}"?`, "This action cannot be undone.", ["Cancel", "OK"]))?.response) {
        await send(url(item.entityName), {objectID: item.objectID}, "DELETE")
    }
}

const openPath = (path) => window.api?.openPath(path)

const openURL = (url) => window.api?.openURL(url)

/**
 * @param {string|undefined} title
 * @param {string|undefined} prompt
 * @param {string|undefined} defaultButton
 * @param {string[]|undefined} options
 * @return {Promise<string|undefined>}
 */
const browse = async (title = undefined, prompt = undefined, defaultButton = undefined, options = undefined) => (await window.api?.showOpenDialog(title ?? "Select folder", prompt, defaultButton ?? "OK", undefined, options ?? ["openDirectory", "promptToCreate"])).filePaths.find(Boolean)

const showOpenPanel = async () => {
    const filePath = await browse("Open Project", "Select the project file", "Open", ["openDirectory"])
    if (filePath) {
        await send(url("open"), {path: filePath}, "POST")
    }
}

/**
 * @param {string} messageText
 * @param {string} informativeText
 * @param {string[]} buttons
 */
const showMessageBox = (messageText, informativeText, buttons) => window.api?.showMessageBox(messageText, informativeText, buttons)

/**
 * @param {Object|undefined} error
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
        height: 380,
        modal: true,
        titleBarOverlay: false
    }
})

/**
 * @param {number} progress
 */
const setProgressBar = (progress) => window.api?.setProgressBar(progress)
