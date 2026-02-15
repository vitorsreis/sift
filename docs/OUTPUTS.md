# Outputs

This document explains how Sift renders tool results and what changes across output modes.

## Normalized Result Shape

Adapters converge on the same base structure before rendering:

```json
{
  "status": "passed|failed|error|changed",
  "summary": {},
  "items": [],
  "artifacts": [],
  "extra": {},
  "meta": {}
}
```

The detailed per-adapter field contract lives in [PAYLOADS.md](PAYLOADS.md).

## Render Formats

Sift currently renders normalized payloads in two formats:

- `json`
- `markdown`

Format can come from:

- `sift.json`
- `--format=<...>` on the CLI

CLI flags win over config.

## Output Sizes

### `compact`

Designed for low-token consumption.

Typical characteristics:

- a flattened summary
- minimal item detail
- no heavy payload sections unless strictly required by the payload factory

### `normal`

Default execution payload.

Typical characteristics:

- `run_id`
- `summary`
- `items`

This is the most balanced mode for terminals and agents.

### `fuller`

Full normalized payload.

Typical characteristics:

- `summary`
- `items`
- `artifacts`
- `extra`
- `meta`

Useful when you need to preserve diffs, contextual metadata, or adapter-specific detail.

## `meta`

Every normalized result is backfilled with common metadata through the meta stamper.

Guaranteed common fields:

- `meta.exit_code`
- `meta.duration`
- `meta.created_at`

Contextual fields may also be present:

- `meta.command`
- `meta.filter`
- `meta.coverage`
- `meta.mode`
- `meta.dry_run`

## `summary`

`summary` is the stable aggregation layer.

Examples:

- tests, failures, errors, skipped
- files and fixers
- vulnerabilities and packages
- changed files and errors

It is the first place to look when deciding whether the run passed and what kind of result it produced.

## `items`

`items` holds the primary findings or events.

Examples:

- failing test cases
- static analysis issues
- code style violations
- advisories
- changed files

When a tool produces many details, `items` is the section that usually matters most for follow-up automation.

## `artifacts`

`artifacts` is for heavier structured payloads that should not always be mixed into `items`.

Current examples:

- Rector file diffs
- applied rectors per file

If a tool does not produce artifacts, the normalized shape still treats the section as present conceptually, even if smaller render modes omit it.

## `extra`

`extra` is reserved for adapter-specific structured data that does not belong naturally in `summary`, `items`, `artifacts`, or `meta`.

Most current adapters do not need it, but it remains part of the shared contract.

## Stored Runs and `view`

When history is enabled, Sift stores normalized runs on disk and later lets you inspect them through `view`.

Available `view` scopes map directly to payload sections:

- `summary`
- `items`
- `meta`
- `artifacts`
- `extra`
- full payload when no scope is provided

This is why the normalized structure is kept stable even when the main execution renderer uses smaller output sizes.

## `--pretty`

`--pretty` affects rendering only.

It does not change:

- adapter parsing
- normalized shape
- history content

It only changes how the final payload is printed.

## `--raw`

`--raw` skips normalization entirely.

When `--raw` is used:

- no normalized payload is generated
- no payload size is applied
- no renderer is used
- no history record is written
- native `stdout`, `stderr`, and exit code are preserved

Use `--raw` when you need the original tool output instead of Sift's normalized contract.
