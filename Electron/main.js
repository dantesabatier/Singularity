if (require("electron-squirrel-startup")) {
    return
}
const { app, nativeTheme, ipcMain, dialog, BrowserWindow, Menu } = require("electron")
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
            spawnedProcess = ChildProcess.spawn(command, args, { detached: true });
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

nativeTheme.themeSource = "dark"

Menu.setApplicationMenu(null)
const createWindow = () => {
    const window = new BrowserWindow({
        minWidth: 1070,
        minHeight: 600,
        webPreferences: {
            webSecurity: false,
            allowRunningInsecureContent: true,
            nodeIntegration: true,
            contextIsolation: true,
            enableBlinkFeatures: "CSSColorSchemeUARendering, OverlayScrollbars, FluentOverlayScrollbars, ElasticOverscrollWin",
            preload: path.join(__dirname, "preload.js")
        },
        darkTheme: true
    })
    // noinspection JSIgnoredPromiseFromCall, JSUnresolvedReference
    window.loadURL("http://localhost:8000")
    window.maximize()
}
app.commandLine.appendSwitch("--enable-features", "OverlayScrollbar, FluentOverlayScrollbars, ElasticOverscrollWin")
app.whenReady().then(() => {
    createWindow()
    ipcMain.handle("showMessageBox", async (event, arg) => dialog.showMessageBox(
        BrowserWindow.fromWebContents(event.sender),
        arg
    ))
    ipcMain.handle("showErrorBox", async (event, arg) => {
        arg ??= arg = {
            localizedDescription: "An unexpected error has occurred",
            localizedFailureReason: undefined,
            localizedRecoverySuggestion: undefined
        }
        /**
         * @type {string}
         */
        const title = arg.localizedDescription ?? arg.message ?? "Ha ocurrido un error inesperado"
        /**
         * @type {string}
         */
        let content = arg.localizedFailureReason ?? arg.localizedRecoverySuggestion ?? ""
        if (!!content && arg.localizedRecoverySuggestion && content !== arg.localizedRecoverySuggestion) {
            if (!content.endsWith(".")) {
                content += "."
            }
            content += `\n${arg.localizedRecoverySuggestion}`
        }
        if (!!arg.userInfo) {
            content += `\n${JSON.stringify(arg.userInfo, null, 4)}`
        }
        if (!!content && !content.endsWith(".")) {
            content += "."
        }
        return dialog.showErrorBox(title, content)
    })
    ipcMain.handle("showOpenDialog", async (event, arg) => await dialog.showOpenDialog(arg))
})
app.on("window-all-closed", () => app.quit())
