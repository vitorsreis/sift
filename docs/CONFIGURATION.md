# Configuration

Default file:

```text
sift.json
```

The v2 config contract uses `$schema`, not a top-level `version`.

`init` writes:

```php
"https://raw.githubusercontent.com/vitorsreis/sift/v" . Sift::VERSION . "/resources/schema.json"
```

Example:

```json
{
  "$schema": "https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json",
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

Relative paths declared inside `sift.json` resolve from the config directory.

Relative runtime paths outside config, including default history and locks, resolve from the project root.

`validate` accepts missing config because runtime defaults are valid.
