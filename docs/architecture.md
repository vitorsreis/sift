# Architecture

Main flow:

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
  -> Output\Renderer
```

## Boundaries

- `Console`: parse, route, preferences, and command handlers.
- `Workspace`: project root, config path, global home, and scope.
- `Config`: JSON parsing, semantic validation, typed config, and config writing.
- `Execution`: binary resolution, process command building, supervision, raw streaming, and TTY behavior.
- `Safety`: pure policies before process execution.
- `Tools`: adapter definitions, command preparation, parsers, status decisions, and tool inspection.
- `History`: one JSON file per run, retention, truncation, and secret redaction.
- `Skills`: source resolution, discovery, selection, target installers, managed blocks, and inventory.
- `Composer`: Composer plugin bridge only.
- `PHAR`: bootstrap and bundled resource access.

## Design Rules

- No large `Kernel` equivalent.
- Tool resolution happens once per execution.
- Adapters do not build shell strings.
- Arrays stay at JSON/input/output edges.
- Critical writes are atomic.
