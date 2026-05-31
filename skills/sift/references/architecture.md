# Architecture

## Flow

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

- `Console`: parsing, routing, handlers, preferences.
- `Workspace`: project root, config file, global home, scope.
- `Config`: schema, typed config, writer.
- `Execution`: binary location, process command building, supervision, raw streaming.
- `Safety`: pre-process policies.
- `Tools`: adapters, parsers, status, inspection.
- `History`: JSON run files, retention, redaction.
- `Skills`: source policy, discovery, target writes, inventory.
- `Composer`: command bridge only.
- `PHAR`: standalone bootstrap and bundled resources.

## Payload

Normal output is JSON:

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

Errors use:

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
