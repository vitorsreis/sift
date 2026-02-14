# Architecture

## Runtime Flow

```text
CLI
  -> OptionParser
  -> ConfigLoader
  -> ToolRegistry
  -> PolicyRunner
  -> ToolAdapterInterface
  -> ProcessExecutor
  -> ResultMetaStamper
  -> ResultPayloadFactory
  -> Renderer
  -> FileRunStore
```

## Main Components

### Console

- `Application` wires commands, tool execution, rendering, and history persistence
- `OptionParser` splits global Sift flags from tool arguments

### Runtime

- `ConfigLoader` loads and validates `sift.json`
- `ConfigDocumentManager` writes config files with the project schema reference
- `ProcessExecutor` runs external commands and can stream recent output lines
- `PolicyRunner` applies execution checks before the process starts
- `ResultMetaStamper` backfills `exit_code`, `duration`, and `created_at`
- `ResultPayloadFactory` renders `compact`, `normal`, and `fuller`
- `FileRunStore` persists full payloads under the configured history path and rotates old files according to `history.max_files`
- `ViewService` slices stored payloads for `sift view`

### Registry and Adapters

- `ToolRegistry` maps the command name to an adapter
- Each adapter implements `ToolAdapterInterface`
- Adapters detect execution context, inject structured flags when possible, and parse native output into the shared payload shape

## Payload Shape

Every normalized execution converges on this structure:

```json
{
  "run_id": "a1b2c3d4",
  "meta": {
    "tool": "phpstan",
    "version": "2.0.0",
    "exit_code": 1,
    "duration": 0.412,
    "created_at": "2026-02-13T08:27:18-03:00"
  },
  "summary": {
    "status": "failed",
    "files": 3,
    "errors": 14
  },
  "items": [],
  "artifacts": [],
  "extra": {}
}
```

## Policies

The current policy chain covers:

- disabled tools
- blocked arguments
- missing binaries
- rector write-mode rejection outside `--dry-run`

Policies fail with `UserFacingException`, which is rendered in the same output format selected for the command.
