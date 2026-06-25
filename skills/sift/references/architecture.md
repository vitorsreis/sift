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
  -> Output\TerminalRenderer | Output\JsonRenderer
```

## Modules

- `Console`: parser, router, command handlers.
- `Workspace`: `cwd`, project root, config path, global root.
- `Config`: schema URL, typed config, writer, semantic validation.
- `Tools`: supported tool definitions, adapters, parsing, status mapping.
- `Safety`: policies that run before any process.
- `Execution`: tool location, process supervision, raw streaming.
- `History`: flat JSON run files, redaction, retention, run id ordering.
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

Full normalized run payload:

```json
{
  "run_id": "0tg3vz210sh8j5",
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

Terminal output is the default human format. JSON is the normalized machine payload and is opt-in with `--json` or config. ANSI styling is controlled with `output.colored` and can be disabled per invocation with `--no-color`. Native output belongs to `--raw`.

History records are flat: `run_id`, payload fields, and `meta` are at the root level. Do not add a `payload` wrapper or `stored_at`.

`help`, `version`, `tools list`, and the root `composer skills` help always render terminal output.
