# Configuration

Default file:

```text
sift.json
```

## Schema

Sift treats `$schema` as editor metadata. `init` writes a schema URL pinned to the installed Sift version, but runtime config loading does not reject older, future, relative, or missing schema references:

```json
{
  "$schema": "https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json",
  "output": {
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

## Validation

`composer sift validate` parses JSON and checks semantic rules. Missing config is valid because runtime defaults are valid.

Invalid config exits with code `3` and a JSON error.
