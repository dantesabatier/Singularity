import {ApplicationContext} from "@/Application/ApplicationContext"
import {ViewController} from "@/Application/ViewController"

type LLMModel = { name: string; identifier: string; tier: string }
// Las opciones de generación se guardan como diccionario plano en el provider
// (p. ej. {num_ctx: 16384, temperature: 0.15}); en el editor se manejan como
// filas {key, value} para poder listarlas, añadirlas y borrarlas.
type LLMProvider = { name: string; identifier: string; url: string; apiKey?: string; models: LLMModel[]; options?: Record<string, string | number> }

export class AIProvidersController extends ViewController {
    private providers: LLMProvider[] = []
    private editingIndex: number | null = null
    private editingModels: LLMModel[] = []
    private editingOptions: Array<{ key: string; value: string }> = []
    private abortController: AbortController | null = null

    public constructor(context: ApplicationContext) {
        super(context)
    }

    protected override supportsCurrentView(): boolean {
        return document.querySelector("#main[data-view=\"preferences\"]") !== null
    }

    protected override setup(): void {
        this.loadProviders()
        this.bindActions()
    }

    protected override viewDidUpdate(): void {
        this.loadProviders()
        this.abortController?.abort()
        this.bindActions()
    }

    private loadProviders(): void {
        this.providers = Array.from(document.querySelectorAll<HTMLElement>(".provider-card")).flatMap((card) => {
            try {
                const data = JSON.parse(card.dataset.provider ?? "null") as LLMProvider | null
                return data ? [data] : []
            } catch {
                return []
            }
        })
    }

    private bindActions(): void {
        this.abortController = new AbortController()
        const signal = this.abortController.signal

        document.getElementById("addLLMProvider")?.addEventListener("show.bs.modal", () => {
            if (this.editingIndex === null) {
                this.clearForm()
            }
        }, { signal })

        document.getElementById("addLLMProvider")?.addEventListener("hidden.bs.modal", () => {
            this.editingIndex = null
            this.editingModels = []
            this.editingOptions = []
        }, { signal })

        document.getElementById("addLLMProvider")?.addEventListener("click", (e) => {
            const btn = (e.target as Element).closest<HTMLElement>("[data-ai-action]")
            if (!btn) {
                return
            }
            switch (btn.dataset.aiAction) {
                case "confirmAddProvider":
                    void this.confirmSaveProvider()
                    break
                case "addModel":
                    this.addModel()
                    break
                case "removeModel": {
                    const idx = parseInt(btn.dataset.modelIndex ?? "-1", 10)
                    if (idx >= 0) {
                        this.removeModel(idx)
                    }
                    break
                }
                case "addOption":
                    this.addOption()
                    break
                case "removeOption": {
                    const idx = parseInt(btn.dataset.optionIndex ?? "-1", 10)
                    if (idx >= 0) {
                        this.removeOption(idx)
                    }
                    break
                }
            }
        }, { signal })

        document.getElementById("ai")?.addEventListener("click", (e) => {
            const btn = (e.target as Element).closest<HTMLElement>("[data-ai-action]")
            if (!btn) {
                return
            }
            const card = btn.closest<HTMLElement>(".provider-card")
            const index = card ? Array.from(document.querySelectorAll(".provider-card")).indexOf(card) : -1
            switch (btn.dataset.aiAction) {
                case "editProvider":
                    if (index >= 0) {
                        this.openForEditing(index)
                    }
                    break
                case "deleteProvider":
                    if (index >= 0) {
                        void this.deleteProvider(index)
                    }
                    break
            }
        }, { signal })
    }

    private openForEditing(index: number): void {
        const provider = this.providers[index]
        if (!provider) {
            return
        }
        this.editingIndex = index
        this.editingModels = [...provider.models]
        this.editingOptions = Object.entries(provider.options ?? {}).map(([key, value]) => ({key, value: String(value)}))
        this.populateForm(provider)
        const label = document.getElementById("addLLMProviderLabel")
        if (label) {
            label.textContent = "Edit Provider"
        }
        const confirmBtn = document.getElementById("pf-confirm-btn")
        if (confirmBtn) {
            confirmBtn.textContent = "Save"
        }
        this.renderModels()
        this.renderOptions()
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("addLLMProvider")!).show()
    }

    private populateForm(provider: LLMProvider): void {
        ;(document.getElementById("pf-name") as HTMLInputElement).value = provider.name
        ;(document.getElementById("pf-identifier") as HTMLInputElement).value = provider.identifier
        ;(document.getElementById("pf-url") as HTMLInputElement).value = provider.url
        ;(document.getElementById("pf-apiKey") as HTMLInputElement).value = provider.apiKey ?? ""
    }

    private clearForm(): void {
        ;(document.getElementById("pf-name") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-identifier") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-url") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-apiKey") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-model-name") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-model-id") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-model-tier") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-option-key") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-option-value") as HTMLInputElement).value = ""
        const label = document.getElementById("addLLMProviderLabel")
        if (label) {
            label.textContent = "Add Provider"
        }
        const confirmBtn = document.getElementById("pf-confirm-btn")
        if (confirmBtn) {
            confirmBtn.textContent = "Add Provider"
        }
        this.editingModels = []
        this.editingOptions = []
        this.renderModels()
        this.renderOptions()
    }

    private addModel(): void {
        const name = (document.getElementById("pf-model-name") as HTMLInputElement).value.trim()
        const identifier = (document.getElementById("pf-model-id") as HTMLInputElement).value.trim()
        const tier = (document.getElementById("pf-model-tier") as HTMLInputElement).value.trim()
        if (!name || !identifier) {
            return
        }
        this.editingModels.push({name, identifier, tier})
        ;(document.getElementById("pf-model-name") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-model-id") as HTMLInputElement).value = ""
        this.renderModels()
    }

    private removeModel(index: number): void {
        this.editingModels.splice(index, 1)
        this.renderModels()
    }

    private renderModels(): void {
        const list = document.getElementById("pf-models-list")
        if (!list) {
            return
        }
        list.innerHTML = ""
        for (const [i, model] of this.editingModels.entries()) {
            const row = document.createElement("div")
            row.className = "d-flex align-items-center gap-2 p-1 rounded"
            row.innerHTML = `
                <span class="ai-model-item-dot tier-${model.tier}"></span>
                <span class="small flex-grow-1">${model.name}</span>
                <code class="ai-model-item-id">${model.identifier}</code>
                <button type="button" class="btn btn-sm btn-icon text-danger flex-shrink-0" data-ai-action="removeModel" data-model-index="${i}">
                    <span class="material-symbols-outlined">close</span>
                </button>
            `
            list.appendChild(row)
        }
    }

    private addOption(): void {
        const key = (document.getElementById("pf-option-key") as HTMLInputElement).value.trim()
        const value = (document.getElementById("pf-option-value") as HTMLInputElement).value.trim()
        if (!key) {
            return
        }
        this.editingOptions.push({key, value})
        ;(document.getElementById("pf-option-key") as HTMLInputElement).value = ""
        ;(document.getElementById("pf-option-value") as HTMLInputElement).value = ""
        this.renderOptions()
    }

    private removeOption(index: number): void {
        this.editingOptions.splice(index, 1)
        this.renderOptions()
    }

    private renderOptions(): void {
        const list = document.getElementById("pf-options-list")
        if (!list) {
            return
        }
        list.innerHTML = ""
        for (const [i, option] of this.editingOptions.entries()) {
            const row = document.createElement("div")
            row.className = "d-flex align-items-center gap-2 p-1 rounded"
            row.innerHTML = `
                <span class="small flex-grow-1">${option.key}</span>
                <code class="ai-model-item-id">${option.value}</code>
                <button type="button" class="btn btn-sm btn-icon text-danger flex-shrink-0" data-ai-action="removeOption" data-option-index="${i}">
                    <span class="material-symbols-outlined">close</span>
                </button>
            `
            list.appendChild(row)
        }
    }

    // Colapsa las filas del editor a un diccionario plano, con coerción numérica:
    // un valor que parsea a número se guarda como número, el resto como texto.
    private collectOptions(): Record<string, string | number> {
        return this.editingOptions.reduce<Record<string, string | number>>((carry, {key, value}) => {
            const name = key.trim()
            if (name) {
                const number = Number(value)
                carry[name] = value.trim() !== "" && !Number.isNaN(number) ? number : value
            }
            return carry
        }, {})
    }

    private async confirmSaveProvider(): Promise<void> {
        const name = (document.getElementById("pf-name") as HTMLInputElement).value.trim()
        const identifier = (document.getElementById("pf-identifier") as HTMLInputElement).value.trim()
        const url = (document.getElementById("pf-url") as HTMLInputElement).value.trim()
        const apiKey = (document.getElementById("pf-apiKey") as HTMLInputElement).value.trim()
        if (!name || !identifier || !url) {
            return
        }
        const pendingName = (document.getElementById("pf-model-name") as HTMLInputElement).value.trim()
        const pendingId = (document.getElementById("pf-model-id") as HTMLInputElement).value.trim()
        const pendingTier = (document.getElementById("pf-model-tier") as HTMLInputElement).value.trim()
        if (pendingName && pendingId) {
            this.editingModels.push({name: pendingName, identifier: pendingId, tier: pendingTier})
        }
        // Incorpora también la fila de opción a medio escribir que el usuario no
        // llegó a confirmar con el botón +, igual que con el modelo pendiente.
        const pendingOptionKey = (document.getElementById("pf-option-key") as HTMLInputElement).value.trim()
        const pendingOptionValue = (document.getElementById("pf-option-value") as HTMLInputElement).value.trim()
        if (pendingOptionKey) {
            this.editingOptions.push({key: pendingOptionKey, value: pendingOptionValue})
        }
        const provider: LLMProvider = {name, identifier, url, models: [...this.editingModels]}
        if (apiKey) {
            provider.apiKey = apiKey
        }
        const options = this.collectOptions()
        if (Object.keys(options).length > 0) {
            provider.options = options
        }
        if (this.editingIndex !== null) {
            this.providers[this.editingIndex] = provider
        } else {
            this.providers.push(provider)
        }
        window.bootstrap.Modal.getInstance(document.getElementById("addLLMProvider")!)?.hide()
        await this.persist()
    }

    private async deleteProvider(index: number): Promise<void> {
        const provider = this.providers[index]
        if (!provider) {
            return
        }
        const result = await this.context.desktopBridge.showMessageBox(`Delete "${provider.name}"?`, "This action cannot be undone.", ["Cancel", "OK"])
        if (!result?.response) {
            return
        }
        this.providers.splice(index, 1)
        await this.persist()
    }

    private async persist(): Promise<void> {
        await this.context.actionDispatcher.dispatch("Synchronize", {
            editorAIProviders: this.providers,
        })
    }
}
