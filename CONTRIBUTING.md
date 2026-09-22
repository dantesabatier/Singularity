# Contributing

Thanks for your interest in Singularity. This document covers how to get the
authoring environment running locally and what a change is expected to carry
with it.

Singularity is not a library. It is the IDE of the Sabatier stack: you design a
data model in it and it generates the Service project that backs the model. That
shapes what a contribution looks like — a change here usually moves the
generated output, not just this tree.

## Requirements

- **PHP 8.5 or newer.** The codebase uses property hooks, asymmetric visibility
  and `#[Override]`; earlier versions will not parse it.
- The extensions `composer.json` requires: `json`, `gettext`, `intl`, `redis`.
- **Redis** on `127.0.0.1`, for the Core Data row cache.
- **MariaDB**, for the store the editor's own managed objects live in.
- **Node 20 or newer**, for the Vite frontend.
- The three sibling frameworks checked out next to this repository:

```
Sabatier/
├── Foundation/
├── CoreData/
├── Service/
└── Singularity/
```

Until the frameworks are published on Packagist, point Composer at those
checkouts rather than editing the tracked `composer.json`:

```bash
composer config repositories.foundation --json '{"type":"path","url":"../Foundation","options":{"versions":{"sabatier/foundation":"1.0.0"}}}'
composer config repositories.coredata   --json '{"type":"path","url":"../CoreData","options":{"versions":{"sabatier/coredata":"1.0.0"}}}'
composer config repositories.service    --json '{"type":"path","url":"../Service","options":{"versions":{"sabatier/service":"1.0.0"}}}'
```

The `versions` option is what lets a path checkout satisfy a `^1.0` constraint;
without it Composer offers the directory as `dev-master` and refuses it. Revert
those three lines before proposing a change — they belong to your machine, not
to the repository.

## Getting set up

```bash
composer install
npm install
```

Copy `.env.example` to `.env` and fill in the database settings. Copy
`.htaccess.example` to `.htaccess`. Neither real file is tracked, and the `.env`
in particular carries credentials and provider API keys — it must never be
committed.

Then run the backend and, if you are working on the frontend, Vite beside it:

```bash
php -S 127.0.0.1:8001 index.php
```

```bash
npm run dev
```

Set `VITE_DEV_SERVER=http://127.0.0.1:5173` in `.env` for hot reload. Leave it
unset and Latte loads the hashed assets from `Build/.vite/manifest.json`
instead.

## Development tools

PHPUnit, Psalm and Rector are declared in `require-dev`, so `composer install`
provides them under `vendor/bin`. Installing them globally works too, and lets
you invoke them by bare name from any of the stack's repositories.

## Running the checks

```bash
vendor/bin/phpunit
```

```bash
vendor/bin/psalm
```

```bash
vendor/bin/rector process src --dry-run
```

```bash
npm run typecheck
```

A change is expected to leave the suite green, Psalm reporting no errors and
`tsc` clean. Rector's dry run is advisory: it proposes modernisations, and not
every proposal is wanted — `rector.php` skips the ones that fight the
framework's design.

Suites live in `tests/` as PHPUnit `TestCase` classes in the `App\Tests`
namespace, split into `Unit` and `Integration`. Warnings and notices fail the
run, so a test that emits either is a test to fix.

## Conventions

The mechanical conventions — file layout, class and property rules, the
collection idioms, when a comment earns its place, what public API has to
document — are shared across the whole Sabatier stack and maintained in
Foundation:

**[Foundation's CONVENTIONS.md](https://github.com/dantesabatier/Foundation/blob/master/CONVENTIONS.md)**

They are deliberately not copied here, so that there is one authoritative
version. [AGENTS.md](AGENTS.md#the-shared-conventions-checklist) notes the two
places this project reads differently from that checklist.

Two that catch people out:

- **Design from the stack.** Foundation already has the collections, paths,
  identifiers and predicates. Before writing an accumulating `foreach`, an
  `array_*` call, `count()` or `in_array()`, look for the method that already
  exists. This applies to tests and throwaway scripts too.
- **`#[Outlet]` properties are uncached on purpose.** The framework injects
  them and they read through to live state. Do not add `??=` to them.

## Changing the generated output

`src/FileWriters/` writes the PHP the editor generates, and `src/Bundles/`
scaffolds and updates project structure. A change to either moves the output of
every project Singularity generates, so it needs a test in
`tests/Integration/` that pins the new output. Those tests exist precisely
because the generated code is the product.

Singularity is modeled in its own editor: `Resources/Singularity.mom` is its
data model and `src/Model/` is generated from it. Editing its own schema is a
real migration, not a text edit — see the notes in `AGENTS.md` before
attempting one.

## Before you open a pull request

- The four checks above pass.
- Commits are scoped to one change, with a message that says why rather than
  what.
- No `.env`, `.htaccess`, `Build/` output or `Library/` scratch space is
  included.
- A change to the generated output carries the integration test that pins it.
- A change a consumer would notice is recorded in `CHANGELOG.md` under
  `## [Unreleased]`, in the same commit — behaviour, a signature, a default, a
  message they read, or what the generator emits. Not test scaffolding, CI or
  analysis configuration.

## Reporting a security problem

Do not open a public issue. See [SECURITY.md](SECURITY.md).

## Versioning

This package follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
What counts as the public API here, what a major release is for, and the checks
that run before a tag are documented once for the whole stack in
[Foundation's VERSIONING.md](https://github.com/dantesabatier/Foundation/blob/master/VERSIONING.md).

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md). By
participating, you are expected to uphold it. Report unacceptable behavior to
`dantesabatier@me.com`.
