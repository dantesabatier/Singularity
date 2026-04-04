// noinspection JSUnusedGlobalSymbols
export class ViewCache {
    private readonly views = new Map<string, string>()

    public get(url: string): string | undefined {
        return this.views.get(url)
    }

    public set(url: string, html: string): void {
        this.views.set(url, html)
    }

    public delete(url: string): void {
        this.views.delete(url)
    }

    public clear(): void {
        this.views.clear()
    }

    public entries(): IterableIterator<[string, string]> {
        return this.views.entries()
    }

    public invalidate(predicate: (url: string, html: string) => boolean): void {
        const keys = Array.from(this.views.entries()).filter(([url, html]) => predicate(url, html)).map(([url]) => url)
        keys.forEach((url) => this.views.delete(url))
    }
}
