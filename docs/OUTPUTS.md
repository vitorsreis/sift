# Outputs

Sift normal mode always emits JSON.

Result payloads go to `STDOUT`.

Sift errors go to `STDERR`.

`--show-process` writes process progress only to `STDERR`.

## Sizes

- `compact`: status and flattened summary.
- `normal`: status, summary, and non-verbose items.
- `full`: complete normalized payload.

## Raw Mode

`--raw` preserves native stdout, stderr, and exit code from the tool.

Raw mode does not normalize payloads and does not write history.

Policies still run before raw execution.

## Debug

`--debug` keeps `STDOUT` JSON unchanged and writes diagnostics to `STDERR`.

Debug output must redact secrets.
