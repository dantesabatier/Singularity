if (require("electron-squirrel-startup")) {
    return
}
const {app, ipcMain, dialog, shell, BrowserWindow, Menu} = require("electron")
const ChildProcess = require("child_process")
const path = require("path")

function handleSquirrelEvent() {
    if (process.argv.length === 1) {
        return false
    }
    const appFolder = path.resolve(process.execPath, "..")
    const rootAtomFolder = path.resolve(appFolder, "..")
    const updateDotExe = path.resolve(path.join(rootAtomFolder, "Update.exe"))
    const exeName = path.basename(process.execPath);
    const spawn = function (command, args) {
        let spawnedProcess
        try {
            spawnedProcess = ChildProcess.spawn(command, args, {detached: true});
        } catch (error) {
        }
        return spawnedProcess
    }
    const spawnUpdate = function (args) {
        return spawn(updateDotExe, args);
    }
    const squirrelEvent = process.argv[1]
    switch (squirrelEvent) {
        case "--squirrel-install":
        case "--squirrel-updated":
            spawnUpdate(["--createShortcut", exeName])
            setTimeout(app.quit, 1000)
            return true;
        case "--squirrel-uninstall":
            spawnUpdate(["--removeShortcut", exeName])
            setTimeout(app.quit, 1000)
            return true
        case "--squirrel-obsolete":
            app.quit()
            return true
    }
}

if (handleSquirrelEvent()) {
    return
}

Menu.setApplicationMenu(null)
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
const createWindow = () => {
    const window = new BrowserWindow({
        ...options,
        width: 600,
        height: 480
    })
    window.webContents.setWindowOpenHandler(() => ({
        action: "allow",
        overrideBrowserWindowOptions: {
            ...options
        }
    }))
    window.on("ready-to-show", () => window.show())
    // noinspection JSIgnoredPromiseFromCall, JSUnresolvedReference
    window.loadURL("http://localhost:8000")
}
app.commandLine.appendSwitch("--enable-features", "OverlayScrollbar, FluentOverlayScrollbars, ElasticOverscrollWin")
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
    /**
     * @type {string}
     */
    const title = error.localizedDescription ?? error.message ?? "Ha ocurrido un error inesperado"
    /**
     * @type {string}
     */
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
    window.on("ready-to-show", () => window.show())
    // noinspection JSIgnoredPromiseFromCall, JSUnresolvedReference
    window.loadURL(arg.url)
})
ipcMain.on("setProgressBar", (event, arg) => BrowserWindow.fromWebContents(event.sender).setProgressBar(arg))
ipcMain.on("openPath", (event, arg) => shell.openPath(arg))
