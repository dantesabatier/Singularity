export interface ShowWindowOptions {
    readonly url: string
    readonly overrideBrowserWindowOptions?: Record<string, unknown>
}

export interface OpenDialogFilter {
    readonly name: string
    readonly extensions: readonly string[]
}

export interface OpenDialogResult {
    readonly filePaths: readonly string[]
}

export interface MessageBoxResult {
    readonly response: number
}

export interface HostApi {
    readonly showWindow?: (options: ShowWindowOptions) => Promise<unknown> | unknown
    readonly showOpenDialog?: (
        title: string,
        prompt: string | undefined,
        defaultButton: string,
        path: string | undefined,
        properties: readonly string[],
        filters?: readonly OpenDialogFilter[]
    ) => Promise<OpenDialogResult> | OpenDialogResult
    readonly showMessageBox?: (
        messageText: string,
        informativeText: string,
        buttons: readonly string[]
    ) => Promise<MessageBoxResult> | MessageBoxResult
    readonly showErrorBox?: (error?: unknown) => Promise<unknown> | unknown
    readonly openPath?: (path: string) => Promise<unknown> | unknown
    readonly openURL?: (url: string) => Promise<unknown> | unknown
    readonly setProgressBar?: (progress: number) => Promise<unknown> | unknown
}

declare global {
    interface Window {
        api?: HostApi
    }
}

export class DesktopBridge {
    private get api(): HostApi | undefined {
        return window.api
    }

    public async showWindow(options: ShowWindowOptions): Promise<void> {
        await this.api?.showWindow?.(options)
    }

    public async showOpenDialog(title: string, prompt: string | undefined, defaultButton: string, path: string | undefined, properties: readonly string[], filters?: readonly OpenDialogFilter[]): Promise<OpenDialogResult | undefined> {
        return this.api?.showOpenDialog?.(title, prompt, defaultButton, path, properties, filters);
    }

    public async showMessageBox(messageText: string, informativeText: string, buttons: readonly string[]): Promise<MessageBoxResult | undefined> {
        return this.api?.showMessageBox?.(messageText, informativeText, buttons);
    }

    public async showErrorBox(error?: unknown): Promise<void> {
        await this.api?.showErrorBox?.(error)
    }

    public async openPath(path: string): Promise<void> {
        await this.api?.openPath?.(path)
    }

    public async openURL(url: string): Promise<void> {
        await this.api?.openURL?.(url)
    }

    public async setProgressBar(progress: number): Promise<void> {
        await this.api?.setProgressBar?.(progress)
    }
}
