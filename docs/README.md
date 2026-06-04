# Documentation

This directory contains Sift's documentation.

## Start Here

- [Getting started](getting-started.md)
- [CLI reference](cli.md)
- [Configuration](configuration.md)
- [Tools](tools.md)
- [Skills](skills.md)
- [Payloads](payloads.md)
- [Architecture](architecture.md)
- [PHAR](phar.md)
- [Contributing](contributing.md)

## Contract Notes

- Output is terminal text by default. Use `--json` for normalized JSON payloads.
- Use `--no-json` to force terminal output when config selects JSON.
- `help`, `version`, and `tools list` always render terminal output.
- `tools list` streams availability and version lines as checks finish.
- Policies run before both normalized and raw tool execution.
- History stores one flat JSON file per run, keyed and sorted by `run_id`.
