# AGENTS.md

## Commands

| Task | Command |
|------|---------|
| PHP static analysis | `composer psalm` (runs `vendor/bin/psalm --config=psalm.xml`, errorLevel 4) |
| PHP code modernization | `vendor/bin/rector process src` |
| PHP lint | `vendor/bin/phpcs --standard=phpcs.xml src/` |
| PHP CS Fixer | `vendor/bin/php-cs-fixer fix --dry-run --diff` |
| PHPStan | `vendor/bin/phpstan analyse -c phpstan.neon` (level 3) |
| Vite dev server | `npm run dev` (port 5173, strict) |
| Frontend typecheck | `npm run typecheck` |
| Frontend production build | `npm run build` (outputs to `Build/`) |
| Electron dev | `cd Electron && npm start` |
| Electron package | `cd Electron && npm run make` |

**Verification order:** `psalm` -> `typecheck` -> test (no automated test suite exists).

## Architecture

### Monorepo-adjacent structure
Singularity depends on three **sibling path repositories** loaded via `composer.json`:
- `../Foundation` → `sabatier/foundation` (object model, KVC/KVO, collections)
- `../CoreData` → `sabatier/coredata` (managed objects, persistence, Redis row cache)
- `../Service` → `sabatier/service` (HTTP framework, attribute-based routing, transformers)

These are NOT in this repo. Do not edit them here; they live as sibling directories.

### Entry point
`index.php` → `Application::shared()->run()` → `Delegate.php` bootstraps everything.

### Routing
**Attribute-based, not URL-pattern-based.** `#[Endpoint]` on classes, `#[Action]` on methods:
```php
#[Endpoint("/", transformers: [HTMLTransformer::class])]  // class = route prefix
#[Action(HTTPRequestMethod::post, transformers: [JSONTransformer::class])]  // method = action
```
The framework reflects on attributes to build routes automatically.

### Outlet properties
`#[Outlet]` marks lazy-initialized computed properties injected by the Service framework. They are auto-wired — do not manually instantiate them.

### Managed Objects
Domain models in `src/Model/` extend `ManagedObject`. Use the context API — **never write raw SQL**:
```php
$fetchRequest = Project::fetchRequest();
$context->fetch($fetchRequest);
$context->object(Entity::class, objectID: $id);
$context->save();  // commits all pending changes
```

### Key directories
- `src/ViewControllers/` — all HTTP endpoints (7 controllers)
- `src/Model/` — CoreData managed object definitions (Project, Model, Entity, Attribute, etc.)
- `src/Bundles/` — project scaffolding transactions
- `src/FileWriters/` — code generation (AST-style `ClassFileAssembler`)
- `src/AI/` — AI model patching pipeline (ModelPatch → processors → apply)
- `src/Responders/` — custom HTTP responders
- `Frontend/ts/` — TypeScript entry point (`main.ts`), uses `@` alias → `Frontend/ts/`
- `Resources/Views/` — Latte templates

### Frontend/Vite integration
- `VITE_DEV_SERVER=http://127.0.0.1:5173` in `.env` enables hot-reload during dev
- Without it, `LatteRenderer` reads `Build/.vite/manifest.json` for hashed assets
- Vite build outputs to `Build/` (gitignored)
- TS alias: `@` → `Frontend/ts/`

### Electron
Electron wraps the PHP server — it does **not** serve the app itself. It opens a `BrowserWindow` to `http://localhost:8001/`. Run PHP backend + Vite first, then Electron.

## Conventions

- `#[Override]` required on all override methods — Psalm enforces this
- PSR-4 namespace: `App\` → `src/`
- Global constants defined in `src/Constants.php` (autoloaded via `files`)
- `*Transaction.php` classes wrap atomic operations with `execute()` method
- Short array syntax `[]` required (php-cs-fixer)
- `get { ... }` = readonly computed property; `get { } set { }` = full accessors

## Environment

- PHP 8.5+ required with: `json`, `gettext`, `intl`, `redis`
- Redis on `127.0.0.1` (default port) — used for row cache and idempotency store
- Apache with mod_rewrite (`.htaccess` rewrites all to `index.php?url=$1`)
- PHP backend runs on `http://localhost:8001/`
- `Library/` directory is gitignored (logs, runtime data)
