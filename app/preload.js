const {contextBridge, ipcRenderer} = require("electron")

contextBridge.exposeInMainWorld("api", {
    showMessageBox: (messageText, informativeText, buttons) => ipcRenderer.invoke("showMessageBox", {
        type: "question",
        message: messageText,
        detail: informativeText,
        buttons: buttons,
        noLink: true
    }),
    showErrorBox: (error) => ipcRenderer.invoke("showErrorBox", error),
    showOpenDialog: (title, message, buttonLabel, defaultPath, properties, filters) => ipcRenderer.invoke("showOpenDialog", {
        title: title,
        message: message,
        buttonLabel: buttonLabel,
        defaultPath: defaultPath,
        properties: properties,
        filters: filters
    }),
    showAboutPanel: (options) => ipcRenderer.send("showAboutPanel", options),
    showWindow: (options) => ipcRenderer.send("showWindow", options),
    setProgressBar: (progress => ipcRenderer.send("setProgressBar", progress)),
})