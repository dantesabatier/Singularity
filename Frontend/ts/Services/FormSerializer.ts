export interface FormSerializationResult {
    readonly body: Record<string, unknown>
    readonly method: string
}

export class FormSerializer {
    public serialize(form: HTMLFormElement): FormSerializationResult {
        const elements = Array.from(form.elements).filter((element): element is HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement => {
            return element instanceof HTMLInputElement || element instanceof HTMLSelectElement || element instanceof HTMLTextAreaElement || element instanceof HTMLButtonElement
        })
        const body = elements.filter((element) => {
            return element.name.length > 0 && element.type !== "submit" && element.name !== "X-Http-Method-Override"
        }).reduce<Record<string, unknown>>((result, element) => {
            result[element.name] = this.normalizeValue(element)
            return result
        }, {})
        const method = elements.find((element) => element.name === "X-Http-Method-Override")?.getAttribute("value") ?? form.method ?? "POST"
        return {
            body,
            method: method.toUpperCase(),
        }
    }

    private normalizeValue(element: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement): unknown {
        if (element instanceof HTMLInputElement && element.type === "checkbox") {
            return element.checked
        }
        const value = "value" in element ? element.value : ""
        if (value === "true") {
            return true
        }
        if (value === "false") {
            return false
        }
        if (value === "" || value === "null") {
            return null
        }
        if (this.isNumeric(value)) {
            return Number(value)
        }
        return value
    }

    private isNumeric(value: string): boolean {
        return value.trim().length > 0 && !Number.isNaN(Number(value))
    }
}
