# Payloads

Full normalized result:

```json
{
  "tool": "string",
  "status": "passed",
  "summary": {},
  "items": [],
  "artifacts": [],
  "extra": {},
  "meta": {}
}
```

Statuses:

- `passed`
- `failed`
- `changed`
- `error`

Error shape:

```json
{
  "status": "error",
  "error": {
    "code": "invalid_usage",
    "message": "Human-readable message.",
    "hint": "Actionable hint."
  }
}
```

Core item fields:

- `type`
- `severity`
- `rule`
- `message`
- `file`
- `line`
- `column`

Common meta fields:

- `exit_code`
- `duration`
- `created_at`
- `command`
- `filter`
- `coverage`
- `coverage_min`
- `mode`
- `dry_run`
- `subcommand`
- `warnings`
