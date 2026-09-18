# Security Policy

## Supported versions

Until 1.0 is tagged, only `master` receives security fixes. Once released, the
latest minor of the current major is supported.

| Version  | Supported |
|----------|-----------|
| `master` | yes       |

## Reporting a vulnerability

**Do not open a public issue for a security problem.** Report it privately, by
either route:

- **GitHub** — [Report a vulnerability](https://github.com/dantesabatier/Singularity/security/advisories/new)
  through the repository's private advisory form.
- **Email** — `dantesabatier@me.com`, with `SECURITY` in the subject.

Please include what you have: affected version or commit, the component
involved, the steps that reproduce it, and what an attacker gains. A proof of
concept helps, but do not delay a report to build one.

You can expect an acknowledgement within 5 days, an assessment with a planned
fix date within 14, and credit in the advisory unless you would rather stay
anonymous. Please give the fix a chance to ship before disclosing publicly; if
a report goes unanswered for 30 days, treat that as consent to disclose.

## What Singularity is, for the purpose of a report

Singularity is an authoring environment, not a public-facing service. It is
expected to run on a developer's machine or a trusted internal host, reachable
by the people who design the models. It is **not** hardened for deployment on
the open internet, and a finding that amounts to "an unauthenticated stranger
who can reach the editor can edit models" is the intended trust model rather
than a vulnerability.

What makes it interesting to an attacker is the other end: it **generates code
and writes files**, and it **drives an LLM agent that holds tool access**. That
is where the scope below concentrates.

## Scope

In scope:

- **Code generation** — model input that escapes into the generated PHP as
  executable code rather than as data. An entity, attribute or relationship
  name that breaks out of an identifier, a string literal or a docblock in the
  output of `src/FileWriters/` is the central concern here: the generated file
  is later executed by the project that was scaffolded.
- **Project scaffolding and file writing** — path traversal through a project
  name, bundle identifier or model name that writes outside the intended
  project directory, and generated `.env` files that land with credentials
  readable beyond the owner.
- **The AI Copilot and its MCP tools** — prompt content reaching
  `EditorController::chat` that drives the tools in `src/MCPTools/` into acting
  outside the selected project, reading files the agent was not given, or
  exfiltrating the configured provider API key. Model data is untrusted input
  to the agent; a tool that treats it as instruction is in scope.
- **Provider credentials** — an API key configured in Preferences that leaks
  into a response, a log, a rendered template or a generated file.
- **The MCP server surface** — an MCP client reaching projects or model
  artifacts beyond what the session was scoped to.
- **Template rendering** — model-supplied values reaching Latte in a way that
  escapes the template's escaping and yields script execution in the editor.

Out of scope:

- Anything that assumes an attacker already has the ability to reach the editor
  and use it as intended. Editing models, generating projects and running the
  copilot is what the editor is for.
- Findings that require an already-compromised host, a modified deployment or
  local filesystem access.
- Denial of service through sheer input volume, or by asking the copilot to
  spend tokens.
- The generated project's own security posture once you have modified it. The
  baseline Service application's guarantees are documented in
  [Service's SECURITY.md](https://github.com/dantesabatier/Service/blob/master/SECURITY.md).
- Reports produced solely by a scanner with no demonstrated impact.

## The frameworks underneath

Singularity is built on [Foundation](https://github.com/dantesabatier/Foundation),
[CoreData](https://github.com/dantesabatier/CoreData) and
[Service](https://github.com/dantesabatier/Service), each of which has its own
repository and its own policy. Report a flaw in one of them against the
repository it belongs to, or here if you are unsure which — a misrouted report
is better than an unsent one.
