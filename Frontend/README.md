# Frontend Architecture

This frontend uses Vite + TypeScript with **view-based bootstraps**.

## Entry Flow

- `Frontend/ts/main.ts` is the only global entry.
- It detects current view and dynamically loads one bootstrap:
  - `Frontend/ts/Bootstraps/welcome.ts`
  - `Frontend/ts/Bootstraps/editor.ts`
  - `Frontend/ts/Bootstraps/viewer.ts`
  - `Frontend/ts/Bootstraps/default.ts`

## Rules

1. Put view-specific code in its own bootstrap (do not import it in `main.ts`).
2. Keep `main.ts` light (shared setup only).
3. Heavy libraries must be imported only where needed.
4. Use controllers/features for behavior; keep Latte declarative.
5. Keep first-paint prelayout scripts minimal and focused.

## Where To Add New Code

- New Editor behavior: `Frontend/ts/Controllers/Editor/*` + `Bootstraps/editor.ts`.
- New Viewer behavior: `Frontend/ts/Controllers/ViewerController.ts` + `Bootstraps/viewer.ts`.
- New Welcome behavior: `Frontend/ts/Controllers/WelcomeController.ts` + `Bootstraps/welcome.ts`.
- Cross-view behavior: shared `Features` or `Services`.

## Validate Changes

- `npm run typecheck`
- `npm run build`

After major changes, check generated chunk sizes in build output.

## Contracts

See `Frontend/CONTRACTS.md` for template-to-controller `data-*` contracts.
