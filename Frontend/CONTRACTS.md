# Frontend Contracts

This document defines the runtime contract between Latte templates and TypeScript controllers.

## View Identity

`#main` must include `data-view`:

- `editor`
- `viewer`
- `welcome`
- `about`
- `preferences`

`Frontend/ts/main.ts` uses this value to load the correct bootstrap.

## Action Attributes

Use explicit action attributes, never inline handlers.

- Editor: `data-editor-action`, `data-editor-change`
- Viewer: `data-viewer-action`
- Welcome: `data-welcome-action`
- About: `data-about-action`

## Payload Attributes

When an action needs payload, pass it through `data-*` with exact key names expected by controllers.

Examples:

- `data-project-id`
- `data-model-path`
- `data-parent` (JSON object)
- `data-item` (JSON object)
- `data-property-name`

## Consistency Rules

1. Template `data-*` key must match controller `dataset.*` expectation.
2. Keep names canonical; reuse existing names instead of inventing variants.
3. New actions must be added in both places:
   - Latte markup
   - Controller switch
