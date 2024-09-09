const { contextBridge, ipcRenderer } = require("electron")

contextBridge.exposeInMainWorld("api", {
    showMessageBox: (messageText, informativeText, buttons) => ipcRenderer.invoke("showMessageBox", {
        type: "question",
        message: messageText,
        detail: informativeText,
        buttons: buttons,
        noLink: true
    }),
    showErrorBox: (error) => ipcRenderer.invoke("showErrorBox", error),
    showOpenDialog: (title, message, buttonLabel, defaultPath, properties) => ipcRenderer.invoke("showOpenDialog", {
        title: title,
        message: message,
        buttonLabel: buttonLabel,
        defaultPath: defaultPath,
        properties: properties
    }),
    showAboutPanel: (options) => ipcRenderer.send("showAboutPanel", options)
})