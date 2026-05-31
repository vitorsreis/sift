# Configuration

Default file:

```text
sift.json
```

## Schema

Sift uses `$schema` as the config contract identifier. `init` writes a schema URL pinned to the installed Sift version:

```json
{
  "$schema": "https://raw.githubusercontent.com/vitorsreis/sift/master/resources/schema.json",
  "output": {
    "size": "compact",
    "pretty": true,
    "show_process": true
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
      "enabled": true,
      "timeout": 1800
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

## Validation

`composer sift validate` checks schema and semantic rules. Missing config is valid because runtime defaults are valid.

Invalid config exits with code `3` and a JSON error.
