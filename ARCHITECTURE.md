# Singularity — Architecture Guide

This document describes how Singularity is built: how a project is opened and scaffolded, how the model is edited and saved, how PHP source is generated from that model, and how the editor's AI copilot operates on the live object graph.

Singularity is a [Sabatier Service](../Service/ARCHITECTURE.md) application. Everything the framework provides — the responder chain, attribute routing, the transformer pipeline, `PersistentSpace`, the MCP server — applies here unchanged and is documented there rather than repeated. This guide covers what Singularity adds on top.

The relationship is worth stating plainly: **Singularity was modeled in its own editor.** The entities in [`src/Model/`](src/Model/) were designed as entities, saved to [`Resources/Singularity.mom`](Resources/Singularity.mom), and generated as PHP by the same pipeline described in §4. Its directory layout is what §3 scaffolds for a new project. Reading this document is reading the output of the tool it documents.

---

## Table of Contents

1. [The Domain Model](#1-the-domain-model)
2. [Application Lifecycle](#2-application-lifecycle)
3. [Projects and Bundles](#3-projects-and-bundles)
4. [Code Generation](#4-code-generation)
5. [Model Versions and Migration](#5-model-versions-and-migration)
6. [View Controllers](#6-view-controllers)
7. [The Frontend](#7-the-frontend)
8. [The AI Copilot](#8-the-ai-copilot)
9. [MCP Tools](#9-mcp-tools)
10. [The Electron Shell](#10-the-electron-shell)

---

## 1. The Domain Model

Singularity edits Core Data models, so its own entities are the vocabulary of Core Data modeling. The object graph:

```
Project → Model → Entity → Attribute / Relationship / FetchedProperty
Entity  also groups: FetchIndex → FetchIndexElement, UniquenessConstraint
Model   also groups: FetchRequestTemplate, Configuration, CompositeType
Property (base of Attribute, Relationship, FetchedProperty) → AccessControl → Role
```

`Property` is abstract, with `Attribute`, `Relationship` and `FetchedProperty` as concrete subentities sharing its table. This matters when reading the code: a fetch against `Property` returns all three unless `includesSubentities` says otherwise.

Two further groups hang off the same model:

- **Mapping** — `ModelMap → EntityMap → PropertyMap`, the editable representation of a Core Data mapping model. Used only for migrations that cannot be inferred (§5).
- **Copilot** — `Conversation → Message → ToolCall`, plus `Attachment` for images. Conversations persist as managed objects like everything else, with token accounting on the conversation.

These are managed objects in Singularity's own store. They are *not* the model being edited — that lives in the target project's `.mom` file. Opening a project reads the `.mom` and materializes it into these entities; saving writes it back out.

---

## 2. Application Lifecycle

[`src/Delegate.php`](src/Delegate.php) is the application delegate. Two phases matter.

**`initialize()`** (static, called before anything else) configures the stack:

- `PersistentStore::$rowCacheClass = RedisRowCache::class` — the row cache backend.
- `ViewController::$rendererClass = LatteRenderer::class` — replaces the framework's default `include`-based renderer with [Latte](https://latte.nette.org).
- `UserDefaults::standard()->register(...)` — the defaults for every preference the editor reads: split sizes, selected view, export options, the LLM provider and model, and the provider list itself.

**`applicationWillFinishLaunching()`** sets three application-level policies:

- `accessPolicy = new PublicAccessPolicy()` — Singularity is a local authoring tool, not a multi-tenant service. There is no login; the access evaluator chain is satisfied unconditionally. **This is the one place where a generated project and Singularity itself deliberately diverge**: a project scaffolded with the security option gets the framework's default policy instead.
- `idempotencyStore = new RedisIdempotencyStore()`
- `viewContext->mergePolicy = MergePolicy::mergeByPropertyObjectTrump()`

`SQLCore::$debugLevel` is also set here, at `none`. Raising it is the fastest way to see what the store is doing — see [When you need to see inside](README.md#when-you-need-to-see-inside).

---

## 3. Projects and Bundles

A *bundle* is the directory a generated project lives in. [`src/Bundles/`](src/Bundles/) owns everything that creates, opens, renames or writes one.

### Transactions

Every operation that touches the filesystem is a `Transaction` — a `final readonly` class with a single `execute()` method:

| Transaction | Does |
|-------------|------|
| `CreateBundleTransaction` | Scaffolds a new project directory |
| `OpenBundleTransaction` | Reads an existing bundle into managed objects |
| `SaveBundleTransaction` | Writes the model back to its `.mom` |
| `RenameBundleTransaction` | Renames the bundle and its internals |
| `SubclassTransaction` | Generates managed-object subclasses (§4) |
| `NewModelVersionTransaction` | Adds a model version (§5) |
| `UpgradeModelTransaction` | Points the store at a new version through a map (§5) |
| `ExportDatabaseTransaction` | Dumps the SQL schema, optionally with data |
| `ExtractLocalizablesTransaction` | Extracts translatable strings |

`CompensableTransaction` extends `Transaction` with a `rollback()` method, implemented by those that must undo their filesystem work if a later step fails.

### Scaffolding

`BundleScaffolder` creates the directory structure (`Resources/`, `Resources/en/`, `src/`) and then writes the base files, each through its own `FileWriter`:

| File | Writer |
|------|--------|
| `Info.plist` | `PlistFileWriter` |
| `composer.json` | `ComposerJsonFileWriter` |
| `.env` | `DotEnvFileWriter` |
| `index.php` | `IndexFileWriter` |
| `cli.php` | `CliFileWriter` |
| `src/Delegate.php` | `DelegateFileWriter` |
| `<Name>.mom` | `ModelFileWriter` |

Three of these take generation options (`BundleGenerationOptions`): `withCORS` and `withJWT` add those blocks to the `.env` — the JWT block carries a freshly generated signing key, not a placeholder — and `withSecurity` changes what `DelegateFileWriter` emits.

Files are written through `createIfMissing`, so scaffolding an existing directory adds what is absent without overwriting what is there.

The generated `index.php` is deliberately three lines: require the autoloader, then `Application::shared()->run()`. Everything else is derived from the model at runtime.

---

## 4. Code Generation

`SubclassFileWriter` generates one PHP class per entity. It does **not** use string templates: it parses what is already on disk, generates what the model implies, and merges the two.

### The merge

`ExistingClassParser` reads the current file and returns its `use` statements, property doc-blocks, class properties, methods and the class declaration itself. `SubclassFileWriter` then regenerates the model-derived parts and reassembles the file, preserving what a programmer added. **Regenerating a subclass does not discard hand-written code** — which is what makes it safe to regenerate after every model change.

### The generators

Each contributes one region of the output:

| Generator | Produces |
|-----------|----------|
| `UseStatementGenerator` | The `use` block, merged with existing imports |
| `PropertyDocBlockGenerator` | `@property` doc-blocks for KVC access |
| `PropertyBlockGenerator` | Typed property declarations and hooks |
| `PropertyAttributeGenerator` | `#[Readable]`, `#[Writable]`, `#[Owner]` field attributes |
| `MagicMethodDocGenerator` | `@method` doc-blocks for relationship mutators |
| `AuthorizableCodeGenerator` | The `Authorizable` implementation, for the user entity |
| `AuthorizableRoleCodeGenerator` | The role entity's implementation |
| `AuthorizationCodeGenerator` | The authorization entity's implementation |
| `ClassDeclarationInjector` | Splices generated blocks into the parsed declaration |
| `ClassFileAssembler` | Assembles the final file |

The three `Authorizable*` generators fire on entities flagged `isAuthorizable`, `isAuthorizableRole` or `isAuthorization` in the model. Marking an entity as the user entity is therefore a modeling decision, and the code that satisfies the framework's auth contract follows from it. They share `EntityRoleCodeGenerator`, whose `reservedPropertyNames` are passed down to `PropertyBlockGenerator` so a member the auth contract requires is never clobbered by one derived from a model attribute of the same name.

Access control declared on a property (`AccessControl → Role`) becomes the `#[Readable]` / `#[Writable]` attributes the framework enforces at runtime — on both REST and MCP.

`ValueObjects/` (`GeneratedProperty`, `GeneratedMethod`, `GeneratedUseStatement`, `PropertyBlock`) are the intermediate representation the generators build before assembly.

---

## 5. Model Versions and Migration

Most model changes need no ceremony. Renaming an attribute, changing its type or its optionality is a **lightweight migration**: Core Data infers the mapping from the difference between the stored model and the current one, and applies it when the store is next opened. Saving is enough. See [CoreData's migration documentation](../CoreData/docs/migrations.md) for what is inferable.

Two transactions exist for the rest:

- **`NewModelVersionTransaction`** adds a version to the `.momd` bundle, invoked from the editor.
- **`UpgradeModelTransaction`** hands the store over to the version a map arrives at. It writes the mapping model where the store will look for it and puts the source version back into the cached model, so the next open finds two versions apart and the map that spans them.

The mapping editor (`MappingController`, `ModelMap → EntityMap → PropertyMap`) exists for migrations that **cannot** be inferred. In practice this is rare: Singularity's own model has never required one.

While a model is being edited the store stays at the version it already holds, so nothing migrates under the programmer's feet mid-edit.

---

## 6. View Controllers

Seven `ViewController`s and one `Responder`, each declaring its route with `#[Endpoint]`:

| Route | Class | Purpose |
|-------|-------|---------|
| `/` | `WelcomeController` | Project list and CRUD (`open`, `create`, `rename`, `remove`) |
| `/Editor` | `EditorController` | The model editor (`save`, `subclass`, `import`, `reorder`, `version`, `seed`, `localize`, `chat`) |
| `/Mapping` | `MappingController` | The mapping-model editor (`upgrade`) |
| `/Viewer` | `ViewerController` | SQL schema viewer (`export`, `moved`) |
| `/Preferences` | `PreferencesController` | Preferences and AI provider config (`synchronize`) |
| `/Help` | `HelpController` | The structured help book |
| `/About` | `AboutController` | Bundle metadata |
| `/Info` | `InfoResponder` | Bundle information |

Two abstract bases carry the shared work: `ProjectController` resolves a `Project` from the query string, and `FetchController` fetches managed objects by ID or predicate.

All eight declare `HTMLTransformer`; actions that answer a fetch declare `JSONTransformer` on the `#[Action]` instead. Following the framework's contract, a responder provides `$data` and never builds a response — see [Service's ARCHITECTURE §8](../Service/ARCHITECTURE.md#8-the-response-pipeline).

Templates are Latte, in [`Resources/Views/`](Resources/Views/), rendered through [`src/LatteRenderer.php`](src/LatteRenderer.php).

---

## 7. The Frontend

TypeScript under [`Frontend/ts/`](Frontend/ts/), built by Vite. The server renders HTML; the frontend hydrates it.

The structure mirrors the backend's: an `Application` owns an `ApplicationContext`, and per-page `ViewController`s are entered through a bootstrap module (`Bootstraps/editor.ts`, `welcome.ts`, …) selected by the page being rendered.

Cross-cutting behavior lives in `Feature` subclasses — a minimal contract of `start()` and `refresh()` — so a behavior attaches to whatever page needs it: autosave, validation, sortable tables, popovers, tooltips, the color picker, predicate highlighting, tree toggles, history and navigation.

Dev mode injects the Vite client when `VITE_DEV_SERVER` is set; production reads hashed filenames from `Build/.vite/manifest.json`. Both paths are handled in the Latte layout.

---

## 8. The AI Copilot

`EditorController::chat` (`POST /Editor/chat`) runs an agent loop against the live model.

The sequence:

1. The request carries `content`, an optional image set, and a conversation snapshot. Provider and model come from the request or fall back to `UserDefaults`.
2. A `Conversation` is materialized from the snapshot; the user's `Message` is appended, with any images written to Application Support and attached as `Attachment` objects.
3. The full history is replayed into `LLMMessage` values — each stored message plus the `LLMResult` of every `ToolCall` it made — so the model sees prior tool results, not just prose.
4. `Provider::find()` resolves the configured provider and builds its `LLMClient` (§ [`src/LLM/`](src/LLM/)).
5. `LLMAgent` runs with Singularity's `ToolRegistry` and an execution policy.
6. Assistant messages and tool calls are persisted back as managed objects, with token usage accumulated on the conversation.

Providers are configured in Preferences and stored in `UserDefaults` under `LLMProvidersPreferencesKey`. `Provider::$redactedDictionaryRepresentation` strips the API key from anything reaching browser code.

The agent runtime itself — loops, budgets, deadlines, write approval, context assembly — belongs to Service and is documented in [LLM.md](../Service/LLM.md).

---

## 9. MCP Tools

[`src/MCPTools/`](src/MCPTools/) holds Singularity's own tools, discovered automatically by the framework and shared by two callers: the MCP server at `/mcp`, and the in-editor copilot through its `ToolRegistry`.

| Tool | Read-only | Effect |
|------|-----------|--------|
| `design_model` | yes | Returns design guidance without applying it |
| `generate_subclasses` | no | Runs `SubclassTransaction` |
| `save_project` | no | Runs `SaveBundleTransaction` |

They operate on live managed objects, resolving a `Project` by `objectID` and reusing the transactions from §3 rather than reimplementing them. All three declare `isOpenWorld = false`.

The framework's own data tools (`describe_model`, `fetch`, `count`, `aggregate`, `group_by`, `create`, `update`, `delete`, …) are derived from the model and need no code here. See [MCP.md](MCP.md) for the annotations and [Service's MCP.md](../Service/MCP.md) for the contract a custom tool honours.

---

## 10. The Electron Shell

[`Electron/`](Electron/) wraps the running PHP server in a native window. It does **not** serve the application: `Electron/src/main.ts` opens a `BrowserWindow` pointing at the local server.

The preload script exposes a `window.api` IPC bridge for the things a browser cannot do — native message boxes, open dialogs, revealing a path in the file manager. The web frontend calls through that bridge when it is present and degrades to browser equivalents when it is not, so the same build runs in both.

---

## See also

| Document | Covers |
|----------|--------|
| [README.md](README.md) | What Singularity is, requirements, getting started |
| [MCP.md](MCP.md) | Singularity's MCP tool annotations |
| [Service/ARCHITECTURE.md](../Service/ARCHITECTURE.md) | The framework: responders, routing, security, MCP, SSR |
| [Service/LLM.md](../Service/LLM.md) | The agent runtime |
| [CoreData/docs/migrations.md](../CoreData/docs/migrations.md) | Inferred, custom and staged migrations |
| [Foundation/PREDICATES.md](../Foundation/PREDICATES.md) | The predicate format-string grammar |
