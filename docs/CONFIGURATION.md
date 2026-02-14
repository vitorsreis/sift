# Configuration

Sift reads `sift.json` from the current working directory by default. You can override the path with `--config=<path>`.

## Schema

Generated configs point to:

```json
"$schema": "./resources/schema/config.schema.json"
```

The local schema file lives at `resources/schema/config.schema.json`.

## Current Shape

```json
{
  "$schema": "./resources/schema/config.schema.json",
  "history": {
    "enabled": true
  },
  "output": {
    "format": "json",
    "size": "normal",
    "pretty": false,
    "show_process": false
  },
  "tools": {
    "phpunit": {
      "enabled": true,
      "toolBinary": "vendor/bin/phpunit",
      "defaultArgs": [],
      "blockedArgs": []
    }
  }
}
```

## Output

- `output.format`: `json` or `markdown`
- `output.size`: `compact`, `normal`, or `fuller`
- `output.pretty`: pretty JSON rendering
- `output.show_process`: live tail of the running process

CLI flags override config values when both are present.

## History

- `history.enabled`: when `true`, normalized runs are stored under `.sift/history`
- `--no-history` disables storage for the current execution only

## Tool Settings

Each entry under `tools` supports:

- `enabled`: enables or disables the tool
- `toolBinary`: explicit binary path or executable name
- `defaultArgs`: arguments always prepended to tool execution
- `blockedArgs`: arguments rejected by policy before execution

## Commands That Write Config

- `sift init`
- `sift add <tool>`

Both commands keep JSON indentation at 2 spaces.
