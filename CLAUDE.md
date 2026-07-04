# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Singularity is the **authoring environment for the Sabatier stack** — an IDE for designing data models and generating complete PHP web service projects. It is itself built with the Sabatier stack, making it a self-referential proof of concept.

## Commands

### PHP Backend
```bash
composer install
psalm                # static analysis (error level 4) — installed globally, NOT in vendor/bin
rector process src   # code modernization — installed globally, NOT in vendor/bin
```

### Frontend (Vite + TypeScript)
```bash
npm install
npm run dev          # Vite dev server on port 5173
npm run build        # production build to Build/
npm run typecheck    # tsc --noEmit
```

### Electron Desktop App
```bash
cd Electron
npm install
npm start            # electron-forge dev mode
npm run make         # package for distribution
npm run typecheck
```

### Runtime Requirements
- PHP 8.5+ with extensions: `json`, `gettext`, `intl`, `redis`
- Redis on `127.0.0.1` (default port)
- Apache with mod_rewrite (`.htaccess` handles routing)
- PHP backend served at `http://localhost:8001/`
- Optional: `VITE_DEV_SERVER=http://127.0.0.1:5173` in `.env` for hot-reload in development

## Architecture

### The Sabatier Stack (local path dependencies)
Three sibling packages (loaded via `../Foundation`, `../CoreData`, `../Service` path repositories):

- **`sabatier/foundation`** — Base object model (`ObjectClass`), KVC/KVO, collections (`ArrayClass`, `Dictionary`, `Set`), `UserDefaults`
- **`sabatier/coredata`** — Persistence layer: managed objects, `PersistentStore`, fetch requests, change tracking, Redis row cache
- **`sabatier/service`** — HTTP framework: `Application`, `ViewController`, attribute-based routing, responder pipeline, transformers

### Routing & Controllers
Routing is **attribute-based**, not URL-pattern-based. The framework reflects on PHP attributes to build routes:

```php
#[Endpoint("Editor", transformers: [HTMLTransformer::class])]
final class EditorController extends ProjectController { /* GET /Editor */ }

#[Action(transformers: [JSONTransformer::class])]
public function save(): void { /* POST /Editor/save */ }

#[Outlet]
public ArrayClass $projects { get { /* auto-injected lazy property */ } }
```

Key controllers in `src/ViewControllers/`:
- `WelcomeController` — root `/`, project CRUD
- `EditorController` — `/Editor`, main IDE interface, model editing, code generation
- `ProjectController` — base class that loads a project from query string reference
- `FetchController` — base for endpoints that return managed objects by ID/predicate

### Responder Data Pattern

A responder **provides data**, it does not construct a response. The framework builds the HTTP response from `$data` and the transformer chain.

- **GET responders / ViewControllers** — override the `$data` property hook (or `#[Outlet]` properties). The framework reads `$data` once, lazily, and passes it through the transformer chain declared on `#[Endpoint]`.
- **Action methods** — perform their work, then assign `$this->data = ...` before returning. The framework passes `$this->data` through the transformer chain declared on `#[Action]`. Do not return a value; assigning `$this->data` is the contract.
- **Override `$response` only** for genuinely atypical behavior: no-body responses (set `$this->statusCode` and leave `$data` null), streaming, or custom status codes. Do not override `$response` when overriding `$data` is sufficient.

### Response Pipeline

Every response goes through two pipelines in sequence:

1. **User pipeline** — the transformers declared on `#[Endpoint]` or `#[Action]` (e.g. `JSONTransformer`, `HTMLTransformer`, `NoCacheHeaderTransformer`).
2. **Infrastructure pipeline** — always runs automatically: `CacheHeaderTransformer`, `ConditionalGetTransformer`, `RateLimitHeaderTransformer`, `SecurityHeadersTransformer`, `CORSResponseTransformer`.

`SecurityHeadersTransformer` is part of the infrastructure pipeline — it applies to every response automatically. Do not add it to a custom transformer list.

### Managed Objects (CoreData Pattern)
Domain models in `src/Model/` extend `ManagedObject`. They use KVC and are automatically persisted via the `PersistentStore`. Never write raw SQL — use fetch requests and predicates:

```php
$projects = $context->fetch(FetchRequest::named('allProjects'));
$context->save();  // commits all pending changes
```

To fetch a single object by `objectID`:

```php
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

$fetchRequest = Entity::fetchRequest();
$fetchRequest->predicate = new ComparisonPredicate(
    Expression::expressionForKeyPath(ManagedObjectObjectIDKey),
    Expression::expressionForConstantValue($id)
);
$entity = $context->fetch($fetchRequest)->first ?? throw new NotFoundException("Not found");
```

The object graph: `Project` → `Model` → `Entity` → `Attribute` / `Relationship` / `UniquenessConstraint`

### PersistentSpace

Any URL whose last path component matches a registered Core Data entity name is handled automatically by `PersistentSpace` — no custom responder needed. It provides `GET` (list, filter, count, aggregate), `POST` (create → 201), `PATCH` (update by `objectID` → 200), and `DELETE` (delete by `objectID` → 204), all with field-level security and ownership enforcement applied automatically.

For `PATCH` and `DELETE` the request body must include `objectID`. For `GET`, query parameters become equality predicates; complex queries (sorting, pagination, aggregates) are passed via `?fetchRequest=<base64-json>`.

Field-level security on managed object properties:
- `#[Readable]` / `#[Writable]` — restricts access to specific fields; by default all fields are readable and writable
- `#[Owner]` — marks the ownership field; PersistentSpace enforces that the authenticated user owns the record on PATCH and DELETE

### Auth & Access Control

The Service framework selects its auth mode at boot:
- **JWT mode** — when `JWT_PRIVATE_KEY` env var is present. Stateless; tokens carry `access` and `refresh` scopes. Default validity: 1800 s (override with `JWT_VALIDITY_TIME_INTERVAL`).
- **Session mode** — default when no JWT key is set. `applicationWillFinishLaunching` in `Delegate.php` is where the access policy is configured.

The access evaluator chain runs in this order (AND short-circuit):
`SessionAuthenticationEvaluator` → `AuthenticationEvaluator` → `JSONWebTokenScopeEvaluator` → `JSONWebTokenAccessTimeEvaluator` → `JSONWebTokenEnabledEvaluator` → `JSONWebTokenVersionEvaluator` → `AuthorizationEvaluator`

When debugging a 401/403, work through this chain. The evaluator that fails is always the one logged first.

Custom responders can override `$accessEvaluator` to use a different chain (e.g., `AuthenticationManager::refresh` drops `AuthorizationEvaluator` and accepts only the refresh-scoped token).

### Application Lifecycle
`index.php` → `Application::shared()->run()` → `Delegate.php` is the entry point. `Delegate::initialize()` (static, called early) registers `UserDefaults`, configures the Redis row cache and `LatteRenderer`. `applicationWillFinishLaunching` sets the access policy and idempotency store.

### Templates
Server-rendered with **Latte** (`latte/latte`). Templates live in `Resources/Views/*.latte`. `LatteRenderer` (in `src/LatteRenderer.php`) bridges the Service framework's renderer interface to Latte.

### Frontend / Vite Integration
- Dev mode: Latte injects Vite client script via `VITE_DEV_SERVER` env var
- Production: Latte reads `Build/.vite/manifest.json` to get hashed asset filenames
- Entry point: `Frontend/ts/main.ts`
- Key JS libs: Cytoscape (entity relationship graph), SortableJS (drag-drop reorder), Highlight.js (code preview), Iro (color picker), Split.js (resizable panes)

### Electron
`Electron/src/main.ts` opens a `BrowserWindow` pointing to `http://localhost:8001/`. The preload script exposes a `window.api` IPC bridge for native dialogs (`showMessageBox`, `showOpenDialog`, `openPath`, etc.). Electron does not serve the app — it wraps the running PHP server.

### Code Generation Pipeline
`src/Bundles/` handles project scaffolding and updates. `src/FileWriters/` generates actual PHP files (managed object subclasses, delegates, `.env`, `.mom` model files). Writers use an AST-style `ClassFileAssembler` rather than string templates.

### AI Model Patching
`src/AI/` implements a pipeline for applying AI-suggested changes to the data model. A `ModelPatch` goes through `*PatchProcessor` stages (classify → normalize → translate → validate) before being applied. `AIModelPatchResponder` handles the HTTP endpoint.

## Key Conventions

- `#[Override]` on any method that overrides a parent — required by Psalm
- `#[Outlet]` marks lazy-initialized computed properties injected by the Service framework
- `get { ... }` syntax = readonly computed property; `get { } set { }` = full property accessors
- `*Transaction.php` classes wrap atomic operations with an `execute()` method
- Constants for preference keys are global functions defined in `src/Constants.php` and autoloaded via `files` in `composer.json`
- PSR-4 namespace: `App\` → `src/`
