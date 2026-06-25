# Payloads

Terminal output is the default. Use `--json` to render the normalized payload.
Terminal output uses ANSI styling by default; set `output.colored=false` or use `--no-color` for plain terminal text.

Full normalized JSON payload for stored and full run output:

```json
{
  "run_id": "0tg3vz210sh8j5",
  "tool": "string",
  "status": "passed",
  "summary": {},
  "items": [],
  "artifacts": [],
  "extra": {},
  "meta": {}
}
```

Compact JSON flattens summary fields and keeps `run_id` first:

```json
{
  "run_id": "0tg3vz210sh8j5",
  "tool": "composer",
  "status": "passed",
  "valid": true,
  "errors": 0,
  "warnings": 0,
  "findings": 0
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
ERROR Unknown option "--bad".
code: invalid_usage
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

History stores a flat JSON run record after secret redaction:

- `run_id` is at the root.
- Payload fields are at the root; there is no `payload` wrapper.
- Metadata lives under `meta`.
- `stored_at` is not written.
- `run_id` is sortable and drives history ordering.

Immediate terminal output is not redacted unless the adapter output itself is redacted.
