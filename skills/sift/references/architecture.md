# Architecture

Sift v2 uses small modules:

- `Console`: parser, routing, command handlers, output preferences.
- `Workspace`: project root, config path, global home, scope.
- `Config`: typed config, schema validation, config writer.
- `Execution`: tool location, command building, process supervision, raw streaming.
- `Safety`: pre-execution policies.
- `Tools`: adapters, parsers, result building, tool inspection.
- `History`: JSON run store and secret redaction.
- `Skills`: source discovery, target installers, managed blocks, inventory.
- `Composer`: Composer plugin bridge.
- `Output`: JSON rendering and payload sizing.

Flow:

```text
Application -> CliParser -> CommandRouter -> Handler -> WorkspaceResolver
  -> ConfigLoader -> ToolRunner -> PolicyPipeline -> ProcessRunner
  -> ToolAdapter -> PayloadSizer -> RunStore -> JsonRenderer
```
