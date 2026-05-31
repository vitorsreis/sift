# Documentation

Sift v2 is a clean rebuild of the PHP agent tooling layer.

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

- Output is JSON unless `--raw` is explicitly used.
- Policies run before both normalized and raw tool execution.
- History stores one JSON file per run.
