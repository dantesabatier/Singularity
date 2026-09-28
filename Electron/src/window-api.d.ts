// noinspection JSUnusedGlobalSymbols

export type ShowWindowPayload = {
    url: string
    overrideBrowserWindowOptions?: Electron.BrowserWindowConstructorOptions
}

export type ErrorBoxPayload = {
    localizedDescription?: string
    localizedFailureReason?: string
    localizedRecoverySuggestion?: string
    userInfo?: unknown
    message?: string
}

declare global {
    interface Window {
        api: {
            showMessageBox: (
                messageText: string,
                informativeText: string,
                buttons: string[]
            ) => Promise<Electron.MessageBoxReturnValue>
            showErrorBox: (error: ErrorBoxPayload) => Promise<void>
            showOpenDialog: (
                title: string,
                message: string,
                buttonLabel: string,
                defaultPath: string,
                properties: Electron.OpenDialogOptions["properties"],
                filters: Electron.OpenDialogOptions["filters"]
            ) => Promise<Electron.OpenDialogReturnValue>
            showAboutPanel: (options: Electron.AboutPanelOptionsOptions) => void
            showWindow: (options: ShowWindowPayload) => void
            setProgressBar: (progress: number) => void
            openPath: (filePath: string) => void
            openURL: (url: string) => Promise<void>
        }
    }
}

export {}
