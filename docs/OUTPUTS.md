# Outputs

This document explains how Sift presents results to the user.

Use this file for:

- render formats
- output sizes
- `view` scopes
- `--pretty`
- `--raw`
- how normalized data is surfaced in terminal output

If you need the structural contract of the normalized result itself, use [PAYLOADS.md](PAYLOADS.md).

## Render Formats

Sift currently renders normalized payloads in two formats:

- `json`
- `markdown`

Format can come from:

- `sift.json`
- `--format=<...>` on the CLI

CLI flags win over config.

## What Gets Rendered

For wrapped tool execution, Sift first produces a normalized result and only then renders it.

What you see on screen depends on:

- the selected format
- the selected output size
- whether the run is a normal wrapped execution or `--raw`

The exact normalized shape and the field contract for `summary`, `items`, `artifacts`, `extra`, and `meta` are documented in [PAYLOADS.md](PAYLOADS.md).

## Output Sizes

### `compact`

Designed for low-token consumption.

Typical characteristics:

- a flattened summary
- minimal detail
- optimized for a first pass
- intended to reduce noise and token use

### `normal`

Default execution payload.

Typical characteristics:

- `run_id`
- `summary`
- `items`

This is the most balanced mode for terminals and agents.

### `fuller`

Full rendered payload.

Typical characteristics:

- `tool`
- `summary`
- `items`
- `artifacts`
- `extra`
- `meta`

Useful when you need to preserve diffs, contextual metadata, or adapter-specific detail.

## Stored Runs and `view`

When history is enabled, Sift stores normalized runs on disk and later lets you inspect them through `view`.

Available `view` scopes map directly to payload sections:

- `summary`
- `items`
- `meta`
- `artifacts`
- `extra`
- full payload when no scope is provided

This is why Sift can render smaller execution payloads while still preserving the fuller normalized result on disk.

## `--pretty`

`--pretty` affects rendering only.

It does not change:

- adapter behavior
- stored history payloads
- the underlying normalized result

It only changes how the final payload is printed.

## `--raw`

`--raw` skips normalization entirely.

When `--raw` is used:

- no normalized payload is generated
- no output size is applied
- no renderer is used
- no history record is written
- native `stdout`, `stderr`, and exit code are preserved

Use `--raw` when you need the original tool output instead of Sift's normalized contract.
