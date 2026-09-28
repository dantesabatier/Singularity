import { contextBridge, ipcRenderer } from "electron"
import type { ErrorBoxPayload, ShowWindowPayload } from "./window-api"

contextBridge.exposeInMainWorld("api", {
    showMessageBox: (messageText: string, informativeText: string, buttons: string[]) => ipcRenderer.invoke("showMessageBox", {
        type: "question",
        message: messageText,
        detail: informativeText,
        buttons: buttons,
        noLink: true
    }),
    showErrorBox: (error: ErrorBoxPayload) => ipcRenderer.invoke("showErrorBox", error),
    showOpenDialog: (
        title: string,
        message: string,
        buttonLabel: string,
        defaultPath: string,
        properties: Electron.OpenDialogOptions["properties"],
        filters: Electron.OpenDialogOptions["filters"]
    ) => ipcRenderer.invoke("showOpenDialog", {
        title,
        message,
        buttonLabel,
        defaultPath,
        properties,
        filters
    }),
    showAboutPanel: (options: Electron.AboutPanelOptionsOptions) => ipcRenderer.send("showAboutPanel", options),
    showWindow: (options: ShowWindowPayload) => ipcRenderer.send("showWindow", options),
    setProgressBar: (progress: number) => ipcRenderer.send("setProgressBar", progress),
    openPath: (filePath: string) => ipcRenderer.send("openPath", filePath),
    openURL: (url: string) => ipcRenderer.invoke("openURL", url)
})
