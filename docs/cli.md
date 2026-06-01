# CLI Reference

## Entrypoints

```bash
composer sift <command>
composer skills <command>
php vendor/bin/sift <command>
php sift.phar <command>
```

All entrypoints call the same application core and should return the same payload for the same arguments.

## Global Options

- `--compact`
- `--full`
- `--pretty`, `-p`
- `--no-pretty`, `-P`
- `--raw`
- `--show-process`
- `--no-show-process`
- `--debug`
- `--history`
- `--no-history`
- `--config=<path>`, `-c <path>`
- `-d <name=value>`

Precedence:

```text
command option > global option > config > default
```

## Commands

- `help`, `--help`, `-h`
- `version`, `--version`, `-V`
- `init`
- `validate`
- `tools list`, `tools ls`
- `skills list`, `skills ls`
- `skills add <source>`
- `skills find [query]`
- `skills init [name]`
- `skills remove <skill>`, `skills rm <skill>`
- `skills update [skill ...]`
- `history list`, `history ls`
- `history view <run_id>`
- `history <run_id>`
- `history view <run_id> summary|items|meta|artifacts|extra`
- `history remove <run_id ...>`, `history rm <run_id ...>`
- `history clear`
- `run <tool> [args]`
- `<tool> [args]`

## Streams

- Normalized result payloads go to `STDOUT`.
- Sift errors go to `STDERR`.
- `--show-process` writes only to `STDERR`.
- `--debug` writes diagnostics to `STDERR` and keeps `STDOUT` unchanged.
- `--raw` passes through native stdout, stderr, and exit code.

## Exit Codes

- `0`: passed
- `1`: findings, failures, diffs, or expected changes
- `2`: operational error
- `3`: usage, config, or policy error before execution
- `130`: detected interruption
