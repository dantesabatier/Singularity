if (require("electron-squirrel-startup")) {
    return
}
const {app, ipcMain, dialog, shell, BrowserWindow} = require("electron")
const path = require("path")

const ENTRY_URL = "http://localhost:8001/"
const options = {
    webPreferences: {
        webSecurity: false,
        allowRunningInsecureContent: true,
        nodeIntegration: true,
        contextIsolation: true,
        enableBlinkFeatures: "CSSColorSchemeUARendering, OverlayScrollbars, FluentOverlayScrollbars, ElasticOverscrollWin",
        preload: path.join(__dirname, "preload.js")
    },
    darkTheme: true,
    backgroundColor: "#212529",
    titleBarStyle: "hidden",
    titleBarOverlay: {
        color: "rgba(33,37,41,0.0)",
        symbolColor: "#ffffff"
    },
    show: false
}

let isNetworkInterceptorSet = false
const setupWindowErrorHandling = (window) => {
    if (!isNetworkInterceptorSet && window.webContents.session) {
        window.webContents.session.webRequest.onHeadersReceived(
            {urls: [ENTRY_URL + "*"]},
            (details, callback) => {
                const shouldCancel = details.resourceType === "mainFrame" && details.statusCode >= 400
                callback({cancel: shouldCancel})
            }
        )
        isNetworkInterceptorSet = true
    }

    window.webContents.on("did-fail-load", async (event, errorCode, errorDescription, validatedURL) => {
        if (errorCode === -3) {
            return
        }
        try {
            const targetUrl = validatedURL || ENTRY_URL
            const response = await fetch(targetUrl)
            const json = await response.json()
            if (json.error) {
                const error = json.error
                let message = error.localizedDescription ?? "Unexpected error"
                let detail = error.localizedFailureReason ?? ""
                if (error.localizedRecoverySuggestion) {
                    detail += detail ? `\n${error.localizedRecoverySuggestion}` : error.localizedRecoverySuggestion
                }
                if (error.userInfo) {
                    detail += detail ? `\n${JSON.stringify(error.userInfo, null, 4)}` : JSON.stringify(error.userInfo, null, 4)
                }
                await dialog.showMessageBox(window, {
                    type: "error",
                    title: message,
                    message: detail || message,
                    buttons: ["OK"]
                })
            }
        } catch (e) {
            await dialog.showMessageBox(window, {
                type: "error",
                title: "Failed to load",
                message: `Failed to load: ${validatedURL || "server"}.\nCode: ${errorCode}`,
                buttons: ["OK"]
            })
        }
    })
}

const createWindow = () => {
    const mainWindow = new BrowserWindow({
        ...options,
        width: 600,
        height: 480
    })
    mainWindow.webContents.setWindowOpenHandler(() => ({
        action: "allow",
        overrideBrowserWindowOptions: {
            ...options
        }
    }))

    setupWindowErrorHandling(mainWindow)

    mainWindow.on("ready-to-show", async () => mainWindow.show())
    // noinspection JSIgnoredPromiseFromCall, JSUnresolvedReference
    mainWindow.loadURL(ENTRY_URL)
}

app.whenReady().then(() => createWindow())
app.on("window-all-closed", () => app.quit())

ipcMain.handle("showMessageBox", async (event, arg) => dialog.showMessageBox(BrowserWindow.fromWebContents(event.sender), {
    ...arg,
    icon: path.join(__dirname, "icon.png")
}))

ipcMain.handle("showErrorBox", async (event, error) => {
    error ??= error = {
        localizedDescription: "An unexpected error has occurred",
        localizedFailureReason: undefined,
        localizedRecoverySuggestion: undefined
    }
    const title = error.localizedDescription ?? error.message ?? "An unexpected error has occurred"
    let content = error.localizedFailureReason ?? error.localizedRecoverySuggestion ?? ""
    if (!!content && error.localizedRecoverySuggestion && content !== error.localizedRecoverySuggestion) {
        if (!content.endsWith(".")) {
            content += "."
        }
        content += `\n${error.localizedRecoverySuggestion}`
    }
    if (!!error.userInfo) {
        content += `\n${JSON.stringify(error.userInfo, null, 4)}`
    }
    if (!!content && !content.endsWith(".")) {
        content += "."
    }
    return dialog.showErrorBox(title, content)
})

ipcMain.handle("showOpenDialog", async (event, arg) => await dialog.showOpenDialog(arg))
ipcMain.on("showAboutPanel", async (event, arg) => {
    app.setAboutPanelOptions({
        ...arg,
        iconPath: path.join(__dirname, "icon.png")
    })
    app.showAboutPanel()
})

ipcMain.on("showWindow", (event, arg) => {
    const window = new BrowserWindow({
        ...options,
        ...arg.overrideBrowserWindowOptions,
        parent: arg.overrideBrowserWindowOptions?.modal ? BrowserWindow.fromWebContents(event.sender) : undefined
    })

    setupWindowErrorHandling(window)

    window.on("ready-to-show", () => window.show())
    // noinspection JSIgnoredPromiseFromCall, JSUnresolvedReference
    window.loadURL(arg.url)
})

ipcMain.on("setProgressBar", (event, arg) => BrowserWindow.fromWebContents(event.sender).setProgressBar(arg))
ipcMain.on("openPath", (event, path) => shell.openPath(path))
ipcMain.handle("openURL", async (event, url) => shell.openExternal(url))
