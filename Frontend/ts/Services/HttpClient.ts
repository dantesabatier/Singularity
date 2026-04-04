export interface RequestOptions {
    readonly method?: string
    readonly body?: unknown
}

export interface ErrorResponse {
    readonly error?: string
}

// noinspection JSUnusedGlobalSymbols
export class HttpClient {
    public async request(url: string, options: RequestOptions = {}): Promise<Response> {
        const headers: Record<string, string> = {
            "X-Requested-With": "XmlHttpRequest",
        }
        if (options.body !== undefined) {
            headers["Content-Type"] = "application/json; charset=utf-8"
        }
        return await fetch(url, {
            method: options.method ?? "POST",
            headers,
            body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
        })
    }

    public async get(url: string): Promise<Response> {
        return await this.request(url, {method: "GET"})
    }

    public async post(url: string, body?: unknown): Promise<Response> {
        return await this.request(url, {method: "POST", body})
    }

    public async delete(url: string, body?: unknown): Promise<Response> {
        return await this.request(url, {method: "DELETE", body})
    }

    public async tryGetErrorMessage(response: Response): Promise<string | undefined> {
        const clone = response.clone()
        try {
            const json = await clone.json() as ErrorResponse
            if (typeof json?.error === "string" && json.error.length > 0) {
                return json.error
            }
        } catch {
        }
        try {
            const text = await response.text()
            return text.length > 0 ? text : undefined
        } catch {
            return undefined
        }
    }
}
