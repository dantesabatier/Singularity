import started from "electron-squirrel-startup"
import {app, BrowserWindow, dialog, ipcMain, shell} from "electron"
import path from "node:path"
import type {ErrorBoxPayload, ShowWindowPayload} from "./window-api"

if (started) {
    app.quit()
}

const ENTRY_URL = "http://localhost:8001/"

interface BackendErrorBody {
    error?: {
        localizedDescription?: string
        localizedFailureReason?: string
        localizedRecoverySuggestion?: string
        userInfo?: unknown
    }
}

const options: Electron.BrowserWindowConstructorOptions = {
    webPreferences: {
        webSecurity: true,
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
const setupWindowErrorHandling = (window: BrowserWindow) => {
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

    window.webContents.on("did-fail-load", async (_event, errorCode, _errorDescription, validatedURL) => {
        if (errorCode === -3) {
            return
        }
        try {
            const targetUrl = validatedURL || ENTRY_URL
            const response = await fetch(targetUrl)
            const json = (await response.json()) as BackendErrorBody
            if (json.error) {
                const error = json.error
                const message = error.localizedDescription ?? "Unexpected error"
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
        } catch {
            await dialog.showMessageBox(window, {
                type: "error",
                title: "Failed to load",
                message: `Failed to load: ${validatedURL || "server"}.\nCode: ${errorCode}`,
                buttons: ["OK"]
            })
        }
    })
}

const createWindow = async () => {
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
    mainWindow.on("ready-to-show", () => mainWindow.show())
    await mainWindow.loadURL(ENTRY_URL)
}

app.whenReady().then(() => createWindow())
app.on("window-all-closed", () => app.quit())

ipcMain.handle("showMessageBox", async (event, arg: Electron.MessageBoxOptions) => {
    const win = BrowserWindow.fromWebContents(event.sender)
    const merged: Electron.MessageBoxOptions = {
        ...arg,
        icon: path.join(__dirname, "icon.png")
    }
    return win ? dialog.showMessageBox(win, merged) : dialog.showMessageBox(merged)
})

ipcMain.handle("showErrorBox", async (_event, error?: ErrorBoxPayload) => {
    const err: ErrorBoxPayload = error ?? {
        localizedDescription: "An unexpected error has occurred",
        localizedFailureReason: undefined,
        localizedRecoverySuggestion: undefined
    }
    const title = err.localizedDescription ?? err.message ?? "An unexpected error has occurred"
    let content = err.localizedFailureReason ?? err.localizedRecoverySuggestion ?? ""
    if (content && err.localizedRecoverySuggestion && content !== err.localizedRecoverySuggestion) {
        if (!content.endsWith(".")) {
            content += "."
        }
        content += `\n${err.localizedRecoverySuggestion}`
    }
    if (err.userInfo) {
        content += `\n${JSON.stringify(err.userInfo, null, 4)}`
    }
    if (content && !content.endsWith(".")) {
        content += "."
    }
    dialog.showErrorBox(title, content)
})

ipcMain.handle("showOpenDialog", async (_event, arg: Electron.OpenDialogOptions) => dialog.showOpenDialog(arg))

ipcMain.on("showAboutPanel", (_event, arg: Electron.AboutPanelOptionsOptions) => {
    app.setAboutPanelOptions({
        ...arg,
        iconPath: path.join(__dirname, "icon.png")
    })
    app.showAboutPanel()
})

ipcMain.on("showWindow", (event, arg: ShowWindowPayload) => {
    const parent = arg.overrideBrowserWindowOptions?.modal
        ? BrowserWindow.fromWebContents(event.sender) ?? undefined
        : undefined
    const window = new BrowserWindow({
        ...options,
        ...arg.overrideBrowserWindowOptions,
        parent
    })

    setupWindowErrorHandling(window)

    window.on("ready-to-show", () => window.show())
    void window.loadURL(arg.url)
})

ipcMain.on("setProgressBar", (event, arg: Parameters<BrowserWindow["setProgressBar"]>[0]) => {
    BrowserWindow.fromWebContents(event.sender)?.setProgressBar(arg)
})

ipcMain.on("openPath", (_event, filePath: string) => {
    void shell.openPath(filePath)
})

ipcMain.handle("openURL", async (_event, url: string) => shell.openExternal(url))
