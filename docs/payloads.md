# Payloads

Full normalized payload:

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

## Errors

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

Context fields such as `tool`, `path`, `run_id`, `argument`, or `suggestions` may be added inside `error`.

## Items

Common fields:

- `type`
- `severity`
- `rule`
- `message`
- `file`
- `line`
- `column`

Item types are centralized. Adapters should not invent new item types without adding catalog coverage.

## History

History stores the full normalized payload after secret redaction. Immediate terminal output is not redacted unless the adapter output itself is redacted.
