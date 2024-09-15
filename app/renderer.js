// noinspection JSUnusedGlobalSymbols,JSUnresolvedReference

const cameCase = (string) => string.replace(/\s(.)/g, $1 => $1.toUpperCase()).replace(/\s/g, "").replace(/^(.)/, $1 => $1.toLowerCase())

const url = (endpoint, parameters) => {
    let url = endpoint
    if (!url.startsWith("/")) {
        url = "/" + url
    }
    if (!!parameters && JSON.stringify(parameters) !== JSON.stringify({})) {
        url += "?" + (() => {
            let array = []
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
 * @param { string } url
 */
const replace = async (url) => {
    let headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    const response = await fetch(url, {
        method: "GET",
        headers: headers
    })
    if (!response.ok) {
        await window.api.showErrorBox((await response.json())?.error)
        return
    }
    const data = await response.text()
    const doc = document.implementation.createHTMLDocument()
    doc.open()
    doc.write(data)
    doc.close()
    const e2 = doc.getElementById("main")
    if (!!!e2) {
        return
    }
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
    register()
}

/**
 * @param { string } url
 */
const push = async (url) => {
    await replace(url)
    await history.pushState({url: url}, "", url)
}

/**
 * @param { string } action
 * @param { object|undefined } body
 * @param { string } method
 */
const send = async (action, body = undefined, method = "POST") => {
    let headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    headers["Content-Type"] = "application/json; charset=utf-8"
    const spinner = document.querySelector(".spinner-border")
    spinner.hidden = false
    const response = await fetch(action, {
        method: method,
        headers: headers,
        body: !!body ? JSON.stringify(body, null, 2) : undefined,
        credentials: "include",
        mode: "no-cors"
    })
    spinner.hidden = true
    const location = new URL(window.location)
    let keys = []
    const entity = action.replace("/", "")
    if (method === "DELETE" || method === "POST") {
        switch (entity) {
            case "Entity":
            case "FetchRequestTemplate":
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
        if (method === "POST") {
            values.push(`${(() => {
                switch (entity) {
                    case "Entity":
                        return "entity"
                    case "FetchRequestTemplate":
                        return "fetchRequest"
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
                }
            })()}=${(await response.json())?.objectID}`)
        }
        location.search = values.join("&")
    }
    await push(location.href)
    if (!response.ok) {
        return await window.api.showErrorBox((await response.json())?.error)
    }
    return response
}

/**
 * @param  { HTMLFormElement } form
 */
const submit = async (form) => {
    let headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    headers["Content-Type"] = "application/json; charset=utf-8"
    const elements = Array.from(form.elements)
    await send(form.action, elements.filter(e => !!e.name && e.type !== "submit" && e.name !== "X-Http-Method-Override").reduce((result, e) => {
        result[e.name] = (() => {
            let value = e.type === "checkbox" ? e.checked : e.value
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
    }, {}), elements.find(e => e.name === "X-Http-Method-Override")?.value ?? form.method)
}

/**
 * @param { object } project
 */
const load = async (project) => await push(url("Editor", {project: project.objectID}))

/**
 * @param { object } project
 */
const subclass = async (project) => await send(url("subclass"), {project: project.objectID})

/**
 * @param { object } project
 */
const model = async (project) => await send(url("import"), {project: project.objectID})

/**
 * @param { string } endpoint
 * @param { object } entity
 */
const add = async (endpoint, entity) => await send(url(endpoint), {
    name: cameCase(endpoint),
    entityPropertyID: entity.objectID
})

/**
 * @param { object } item
 */
const remove = async (item) => {
    if ((await window.api.showMessageBox(`Remove "${item.name ?? item.propertyName ?? item.stringValue}"?`, "This action cannot be undone.", ["Cancel", "OK"])).response) {
        await send(url(item.entityName), {objectID: item.objectID}, "DELETE")
    }
}

// noinspection SpellCheckingInspection
const showPreferences = () => window.open("/Preferences", "_blank", "popup=true, noopener, noreferrer, width=600, height=400")
const showAboutPanel = (options) => window.api.showAboutPanel(options)
const setProgressBar = (progress) => window.api.setProgressBar(progress)