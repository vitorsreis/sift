# Architecture

Use this file only when changing Sift internals or reasoning about payload contracts.

## Runtime Flow

```text
Entrypoint
  -> Console\Application
  -> Console\CliParser
  -> Console\CommandRouter
  -> Console\Commands\CommandHandler
  -> Workspace\WorkspaceResolver
  -> Config\SiftConfig
  -> Tools\ToolRunner
  -> Safety\PolicyPipeline
  -> Execution\ProcessRunner
  -> Tools\ToolAdapter
  -> Output\PayloadSizer
  -> History\RunStore
  -> Output\JsonRenderer
```

## Modules

- `Console`: parser, router, command handlers.
- `Workspace`: `cwd`, project root, config path, global root.
- `Config`: schema URL, typed config, writer, semantic validation.
- `Tools`: supported tool definitions, adapters, parsing, status mapping.
- `Safety`: policies that run before any process.
- `Execution`: tool location, process supervision, raw streaming.
- `History`: JSON run files, redaction, retention.
- `Skills`: source policy, discovery, targets, managed metadata.
- `Composer`: Composer command bridge only.
- `PHAR`: standalone bootstrap and bundled resources.

## Config Contract

- v2 only.
- `$schema` is editor metadata for schema-aware tools.
- `init` writes `https://raw.githubusercontent.com/vitorsreis/sift/v{Sift::VERSION}/resources/schema.json`.
- Missing config is valid and uses defaults.
- Runtime loading accepts older, future, relative, or missing schema references.
- Schema, runtime loader, and docs must describe the same fields.

## Payload Contract

Normal output:

```json
{
  "tool": "pest",
  "status": "failed",
  "summary": {},
  "items": [],
  "artifacts": [],
  "extra": {},
  "meta": {}
}
```

Error output:

```json
{
  "status": "error",
  "error": {
    "code": "invalid_usage",
    "message": "...",
    "hint": "..."
  }
}
```

JSON is the only normalized output format. Native output belongs to `--raw`.
