// noinspection JSUnusedGlobalSymbols,JSUnresolvedReference

function url(endpoint, parameters) {
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
async function replace(url) {
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
async function push(url) {
    await replace(url)
    await history.pushState({
        url: url,
    }, "", url)
}

/**
 * @param { string } action
 * @param { object|undefined } body
 * @param { string } method
 */
async function send(action, body = undefined, method = "POST") {
    let headers = {}
    headers["X-Requested-With"] = "XmlHttpRequest"
    headers["Content-Type"] = "application/json; charset=utf-8"
    const spinner = document.querySelector(".spinner-border")
    spinner.hidden = false
    const response = await fetch(action, {
        method: method,
        headers: headers,
        body: !!body ? JSON.stringify(body, null, 2) : undefined
    })
    spinner.hidden = true
    const location = new URL(window.location)
    if (method === "DELETE") {
        let keys = []
        switch (action.replace("/", "")) {
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
        location.search = keys.map(k => k + "=" + location.searchParams.get(k)).join("&")
    }
    await push(location.href)
    if (!response.ok) {
        await window.api.showErrorBox((await response.json())?.error)
    }
}

/**
 * @param  { HTMLFormElement } form
 */
async function submit(form) {
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

function showPreferences() {
    window.open("/Preferences", "_blank", "popup=true, noopener, noreferrer, width=600, height=400")
}

/**
 * @param { object } project
 */
async function load(project) {
    await push(url("Editor", {
        project: project.objectID
    }))
}

/**
 * @param { object } project
 */
async function subclass(project) {
    console.log(JSON.stringify(project, null, 2))
    const response = await window.api.showMessageBox("Create managed object subclass?", "This action cannot be undone.", ["Cancel", "OK"])
    if (response.response) {
        await send(url("subclass"), {
            project: project.objectID
        })
    }
}

/**
 * @param { object } project
 */
async function importModel(project) {
    const response = await window.api.showMessageBox("Import model?", "This action cannot be undone.", ["Cancel", "OK"])
    if (response.response) {
        await send(url("import"), {
            project: project.objectID
        })
    }
}

/**
 * @param { object } item
 */
async function remove(item) {
    const response = await window.api.showMessageBox("Remove \"" + (item.name ?? item.propertyName ?? item.stringValue) + "\"?", "This action cannot be undone.", ["Cancel", "OK"])
    if (response.response) {
        await send(url(item.entityName), {
            objectID: item.objectID
        }, "DELETE")
    }
}