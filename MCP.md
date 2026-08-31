# MCP tools

Singularity's custom tools are discovered in `src/MCPTools/` and shared by the MCP server and the editor's agent. The Service framework supplies the generic data tools separately.

| Tool | Read-only | Interaction domain | Write effects |
|------|-----------|--------------------|---------------|
| `design_model` | Yes | Selected project model | Returns design guidance without applying it. |
| `generate_subclasses` | No | Selected project and generated bundle | Can overwrite model classes and generated bundle files. |
| `save_project` | No | Selected project and generated bundle | Can overwrite generated model and mapping files. |

All three declare `isOpenWorld = false`, published as MCP `openWorldHint: false`. The writing tools retain the conservative `destructiveHint: true` and `idempotentHint: false`: file generation is not merely additive, and repeated calls are not promised to have no additional effects.

`design_model` inherits the same destructive and idempotent defaults, but those hints are meaningful only for writing tools; its `readOnlyHint: true` describes the operation.

These annotations describe the catalogue; they do not grant approval, authorize retries or enable result caching. Existing authorization and the agent's write-approval policy still apply. The provider-specific LLM payloads do not include MCP annotations.
