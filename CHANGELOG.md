# Changelog

All notable changes to Singularity are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- The Anthropic provider offers Opus 5, Sonnet 5 and Haiku 4.5, and a fresh install starts on Opus 5. A model already chosen in preferences is kept; this is the list a provider carries before anyone edits it.

### Fixed

- Generated projects require the stack from Packagist. Every `composer.json` Singularity emitted named the three packages by relative path, so a generated project resolved only on a machine with the stack checked out beside it.
- `Info.plist` reports the released version. It still read `0.7`, the version this carried while it was a private project, through the `1.0.0` tag.

## [1.0.0] - 2026-09-18

Singularity has been developed since 2023 and shipped as an Electron application at bundle version 0.7. This changelog starts here rather than reconstructing that history: the entries below describe what the editor does, and then record the identifiable work of the current cycle — the mapping model editor, the copilot, the security scoping, and the fixes found while covering the tree — grouped by the area they touched rather than listed chronologically.

### Added

- The model editor: entities, attributes, relationships, fetched properties, fetch indexes, uniqueness constraints, fetch request templates, configurations and composite types, edited against Singularity's own object graph and saved back to the project's `.mom`.
- Project scaffolding. Creating a project emits the whole bundle — `Info.plist`, `composer.json`, the HTTP and CLI entry points, the application delegate, the `.mom` model, and a `.env` carrying the database settings and, if JWT was asked for, a freshly generated signing key. Managed-object subclasses are generated from the model on demand, and regeneration preserves hand-written code.
- Access control on the model. Roles, entity-level controls, and attribute-based conditions travel with the property they guard, so the REST surface and the MCP catalogue a generated project exposes enforce the same rules without either being declared twice.
- The AI copilot: conversations, messages and tool calls persisted as managed objects with token accounting on the conversation, over any configured LLM provider, with per-provider generation options and the model switchable mid-conversation.
- MCP tools for driving the editor from an agent — `design_model`, `generate_subclasses` and `save_project` — with titles and descriptions localized from the tool vocabulary.
- The mapping model editor, for the migrations inference cannot express. The screen opens on a pair of model versions the programmer has already declared and seeds itself from what inference works out, so what is left to do is only what inference could not say; when it can express nothing at all, it names the entity that stopped it. It decides the mapping type the two sides imply, the version hashes, and the property names as menus rather than free text, and it reports the destination attributes no map covers. A generated mapping model file can be deleted along with the map.
- Explicit model versioning. Every save used to overwrite the single model file, so only one version of a model existed at a time — while a custom migration is by definition a pair of versions, and the source has to come from somewhere the store cannot supply, since the SQL store stays on the programmer's machine while the model travels to the server. Freezing a version is now its own act, because deciding to migrate is the same decision; saving stays saving. The frozen version takes a numbered name and the work in progress keeps the package's own.
- Scaffolding for the Apache configuration. A new project received every file it needed to run except the `.htaccess` that routes requests into `index.php`, which the programmer had to write by hand without a reference before the generated application would answer anything. `HtaccessFileWriter` emits it with the rest, and unlike the checked-in example it can point `error_log` at the project's own `Library/Logs/errors.log`, because it knows the bundle's absolute path — and it creates that directory first, since Apache does not create directories and a path it cannot write to fails silently, costing the log exactly when it is needed. A configuration the developer has already tuned survives a later scaffolding.
- Advisory MCP annotations on the editor's own tools, declaring each one's effect and whether it reaches outside the application's data.
- Opt-in Anthropic prompt-prefix caching, enabled in the provider factory.
- Run outcomes reported to the chat panel. A run that stops on an iteration cap, a deadline, a budget or a refusal now says so, and a tool result that arrived without the text round that would have carried it is preserved rather than dropped.
- The unit testing infrastructure and the first tests. Singularity was the only project in the stack without any; the scaffolding follows Service's layout with CoreData's strict flags, so a warning cannot go unnoticed. Two support cases carry the recipes — a temporary directory with recursive teardown, and an in-memory CoreData stack over the app's own model.
- `ARCHITECTURE.md`, covering what Singularity adds over the framework: bundles and transactions, the code generation pipeline and how regeneration preserves hand-written code, model versions and migration, the frontend, the copilot and its MCP tools. It defers to Service, CoreData and Foundation for what they already document, and the README points there rather than at `CLAUDE.md`, which is agent instructions.
- `AGENTS.md` and `CLAUDE.md` point at [Foundation's `CONVENTIONS.md`](https://github.com/dantesabatier/Foundation/blob/master/CONVENTIONS.md) for the mechanical conventions shared across the stack, by absolute URL rather than as a vendored copy — four copies of one checklist drift, and then nobody knows which is authoritative. `src/` satisfies every rule that document can be searched for, with one exception written down rather than left to be rediscovered: `#[Outlet]` properties are uncached on purpose. The document requires a public computed property to reflect current state on every read, so caching one makes it stale, which is exactly the Outlet contract and the reason `??=` does not belong on them.
- The MIT `LICENSE.md` the Composer manifest had always declared.

### Changed

- Installation-specific configuration is out of the repository. `.env` and `.htaccess` carried values belonging to one machine — database credentials, and an absolute `error_log` path — and are now untracked, with `.env.example` and `.htaccess.example` documenting every setting a fresh clone has to fill in. `Library/Logs/` is gitignored but has to exist before Apache can write into it, so a `.gitkeep` holds the directory.
- A store is migrated when the map is ready, not when the model changes. Editing a model emitted it to disk, which left the store one version behind and asked Core Data to migrate on the next request — before any map existed for that pair of versions. A change inference could not express failed there, and the project stopped opening while the map that would have covered it was still being written. Saving now records the emitted model as the one the store holds, so the two agree and nothing migrates; handing the store over is a separate act.
- The mapping editor's action is Upgrade.
- Grouping in the chat panel. The agent loop persists one message per round, so a reply that queried three times rendered as three separate "Used 1 tool" blocks; consecutive text-less assistant messages now fold onto the next message carrying text, and the panel shows one "Used 3 tools" group. Both render paths do it — Latte paints the persisted history on load, and the controller appends after a send.
- The relationship inspector warns when a relationship has a destination but no inverse, inline under the Inverse selector and as an icon on each affected row of the relationships table, since the object graph is left without the back-link Core Data relies on to stay consistent.
- A typed entity warns about the interface-required properties it still lacks, computed from the same `reservedPropertyNames` the subclass writer uses.
- Schema name resolution is cached.

### Removed

- The ordering hint on the Relationship view.

### Fixed

**The model editor**

- A many-to-many can no longer be marked ordered. The order of a to-many is kept in a column on the destination table, so it can only exist when the inverse is to-one; a many-to-many lives in a correlation table that has no such column, and asking the SQL store to fault one ordered crashed while rendering the inspector — `AccessControl.roles` was marked ordered, so every attempt to read it failed. `Relationship` answers `canBeOrdered` from the inverse's cardinality, `validateIsOrdered` refuses the flag when the answer is no, and the checkbox is disabled.
- An entity type change persists. The Entity Type radios sat outside the inspector `<form>`, so the autosave never submitted them and clicking a type silently did nothing.
- Saving a `ModelMap` no longer raises `Call to undefined function`. `preg_replace("/\s+/", "", $this->name)` is not a callable, so the pipe operator evaluated it and then tried to invoke the result as a function — every save of a `ModelMap` failed, and 39 tests errored with it. The replacement is wrapped in a closure so the piped value is its argument.
- `position` sorts ascend. Foundation corrected `SortDescriptor::compareObject`, which had been multiplying the spaceship result by `-1` for an ascending descriptor; every `position` sort here had been written against that behaviour, passing `ascending: false` to obtain what was effectively ascending order, and so began sorting descending once Foundation was right. The compensation is gone rather than re-inverted.
- Assigning a preset role in the role popover works, and a role is unassigned from an access control rather than deleted.
- Disabled access controls are skipped during generation.
- A generated project gets a valid Composer name and bundle identifier, through `slug()`.

**The copilot**

- The design assistant can perform the writes that are its job. The framework began denying every state-changing tool call unless an `LLMExecutionPolicy` approves that concrete call, and `EditorController` built the agent without one, so a run stopped at `writeApprovalRequired` as soon as it tried to create an entity. The policy enumerates the editor's writes by name rather than accepting any, so a tool added to the catalogue later arrives denied and the decision to admit it is made then. `design_model` only reads — it finds the project and returns instructions and an output format without touching the context — so it declares itself read-only, which also restores its run cache; `generate_subclasses` and `save_project` do write and stay that way.
- `design_model` authorizes the entity and narrows the fetch before reading, the way every read tool in the framework does. It built the project's `FetchRequest` and executed it without the first two guards, the only read tool in the editor departing from the pattern. This is a convention fix, not an exposure: this version of Singularity has no users and no login — it opens as a desktop editor, so the delegate installs `PublicAccessPolicy` and both guards return immediately, and with no authenticated subject there are no rows to narrow. It is corrected anyway, because the editor generates Sabatier projects and the code of its own tools is the reference for how one is written.
- Selecting a conversation has an effect. The client sent `PATCH /Project {objectID, selectedConversationID}`, but the relationship is named `selectedConversation`; `PersistentSpace` dropped the unknown key, answered `200`, and the client reloaded into the conversation it started from.
- The new conversation button works. Creating one posted a `model` field that `Conversation` has no attribute for, so `PersistentSpace` failed KVC validation and answered `500` — and the response was discarded by a bare `if (!response.ok) return`, which is why the button appeared inert with nothing surfaced.
- A failed conversation request is surfaced instead of swallowed. All three conversation methods bailed out on `if (!response.ok) return`, making a failing request indistinguishable from a dead button; that is how the `500` above went unnoticed, with the service reporting the exact reason and the client throwing it away.
- An LLM error is visible on the first message of a new conversation. The panel rendered `#chat-messages` only once a conversation was selected, and every render path starts by looking that container up and returns silently when it is missing — so both the optimistic user bubble and any error bubble were built and then discarded.
- The chat model picker shows a model the conversation's provider actually has. The panel took its provider from the conversation but its model from the global preferences, so with a conversation pinned to one provider and the preference holding another's model, the picker listed the provider's models while the label read the other's and the active dot sat on a hidden option, leaving nothing visibly selected.
- Deleting the provider that held the default no longer leaves the preference dangling. The default was reassigned only when another provider remained, and it picked the first one even if that provider had no models; it now picks the first remaining provider that has models, and clears both preferences when none do, so the chat falls back to its own defaults rather than a stale identifier.
- The LLM provider modal is outside the form it was nested in.

**Documentation**

- The predicate syntax page promises what the parser does, verified against it. `LIKE` was documented as a wildcard match with `?` and `*`; it compares the whole string literally and expands no wildcard in either syntax, which is what makes an exact-looking `LIKE` find a value holding a literal `%` or `_` — common in SKUs and folios — and what keeps the in-memory and SQL evaluations agreeing. The page now points at `BEGINSWITH`/`CONTAINS`/`ENDSWITH` for a substring search and `MATCHES` for a pattern. The modifier list offered `[l]`, `[cl]`, `[cdl]` and `[cdnl]`; only `[c]`, `[cd]` and `[cdn]` exist, and `StringPredicateOperator` rejects the locale-sensitive option outright. A Known limits section covers four constraints that were undocumented and read as bugs otherwise: a leading parenthesis always opens a predicate group, a reducing operator cannot follow a flattening one, a collection operator needs a Foundation collection rather than a native array, and `@sum` has to come last in a key path inside a predicate.
- The README opens with the work the reader recognises — declaring routes and controllers in every project, then declaring all of it again, permissions included, to expose the same data to an agent — rather than by naming the stack, which answers a question not yet asked. MariaDB is listed among the requirements, which `.env` had required all along, and the claim that routes exist the moment a controller is defined is corrected to the custom-responder case it actually describes. A new section documents the observability both frameworks ship and neither documented: `Predicate::$debugDefault` prints a compound predicate as the tree it parsed into, and `SQLCore::$debugLevel` is a six-step scale from the raw statement to the engine's execution plan.
