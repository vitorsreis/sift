# Configuration

Default file:

```text
sift.json
```

## Schema

Sift treats `$schema` as editor metadata. `init` writes a schema URL pinned to the installed Sift version, but runtime config loading does not reject older, future, relative, or missing schema references:

```json
{
  "$schema": "https://raw.githubusercontent.com/vitorsreis/sift/v2.1.0/resources/schema.json",
  "output": {
    "format": "terminal",
    "size": "compact",
    "pretty": true,
    "show_process": false
  },
  "history": {
    "enabled": true,
    "path": ".sift/history",
    "max_files": 50,
    "max_age_days": 30,
    "max_bytes_per_run": 1048576,
    "redact_secrets": true
  },
  "tools": {
    "*": {
      "enabled": true
    },
    "phpstan": {
      "binary": "vendor/bin/phpstan",
      "blocked_args": [],
      "timeout": 0
    }
  }
}
```

## Path Rules

- Relative paths inside `sift.json` resolve from the config file directory.
- Runtime defaults such as history and locks resolve from the project root.
- Global scope uses `SIFT_HOME` or `~/.sift`.
- `history.max_age_days` is optional. `init` writes `30`; omitting the field disables age-based retention.
- `output.format` accepts `terminal` or `json`. The default is `terminal`.
- Tool timeouts default to `1800` seconds. Set `timeout` to `0` to disable the timeout.
- `--json` and `--no-json` override `output.format`.
- `help`, `version`, and `tools list` always render terminal output.
- `output.pretty` affects JSON output only.

## Validation

`composer sift validate` checks the document against the bundled JSON Schema and then applies semantic runtime rules. Missing config is valid because runtime defaults are valid.

Invalid config exits with code `3`. Errors are terminal text by default and JSON with `--json`.
