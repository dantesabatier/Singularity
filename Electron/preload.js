const { contextBridge, ipcRenderer } = require("electron")

contextBridge.exposeInMainWorld("api", {
    showMessageBox: (messageText, informativeText, buttons) => ipcRenderer.invoke("showMessageBox", {
        type: "question",
        message: messageText,
        detail: informativeText,
        buttons: buttons
    }),
    showErrorBox: (error) => ipcRenderer.invoke("showErrorBox", error),
})