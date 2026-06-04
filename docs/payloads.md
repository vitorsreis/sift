# Payloads

Terminal output is the default. Use `--json` to render the normalized payload.

Full normalized JSON payload:

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

Output sizes in terminal and JSON modes:

- `compact`: renders `tool`, `status`, and flattened `summary` fields.
- `normal`: renders `tool`, `status`, `summary`, non-verbose `items`, and `meta`.
- `full`: renders the complete normalized payload.

Statuses:

- `passed`
- `failed`
- `changed`
- `error`

## Errors

Terminal errors use short labeled lines:

```text
error invalid_usage
message: Unknown option "--bad".
hint: Run "sift help" to list available commands.
```

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
