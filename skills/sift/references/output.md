# Output and history

Use this reference to choose an output mode or inspect a stored run.

## Output modes

| Mode        | Result                                                         |
|-------------|----------------------------------------------------------------|
| Default     | Terminal output with summary, non-verbose items, and metadata. |
| `--compact` | Tool, status, and flattened summary fields.                    |
| `--full`    | Complete normalized payload.                                   |
| `--json`    | Normalized JSON instead of terminal rendering.                 |
| `--raw`     | Native stdout, stderr, and exit code.                          |

Do not combine `--compact` with `--full`, or `--json` with `--raw`. Use `--pretty` or `--no-pretty` only to control JSON
formatting.

`help`, `version`, `tools list`, and root skills help always use terminal output and ignore `--json`.

## Streams and color

- Result output goes to `STDOUT`.
- Errors, `--show-process`, and `--debug` diagnostics go to `STDERR`.
- Set `output.colored=false` in `sift.json` to disable terminal color by default.
- Pass `--no-color` to disable color for one command.

## JSON payload

Full output and stored history use this shape:

```json
{
    "run_id": "0tg3vz210sh8j5",
    "tool": "pest",
    "status": "passed",
    "summary": {},
    "items": [],
    "artifacts": [],
    "extra": {},
    "meta": {}
}
```

Statuses are `passed`, `failed`, `changed`, or `error`. Compact JSON keeps `run_id`, `tool`, and `status`, then flattens
the summary fields.

## History

```bash
composer sift history list [--limit=<n>] [--offset=<n>]
composer sift history view <run_id>
composer sift history view <run_id> summary
composer sift history view <run_id> items
composer sift history view <run_id> meta
composer sift history view <run_id> artifacts
composer sift history view <run_id> extra
composer sift history remove <run_id>...
composer sift history clear
```

History stores one flat, secret-redacted JSON file per run. `run_id` is sortable and remains at the payload root. Raw
mode does not write history.
