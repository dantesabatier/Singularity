export class RouteBuilder {
    public build(endpoint: string, parameters?: Record<string, unknown>): string {
        let url = endpoint.startsWith("/") ? endpoint : `/${endpoint}`
        if (!parameters || Object.keys(parameters).length === 0) {
            return url
        }
        const searchParams = new URLSearchParams()
        Object.entries(parameters).forEach(([key, value]) => {
            if (value === undefined) {
                return
            }
            if (value === null) {
                searchParams.set(key, "null")
                return
            }
            searchParams.set(key, String(value))
        })
        const query = searchParams.toString()
        if (!query) {
            return url
        }
        return `${url}?${query}`
    }
}
