# Commands

## Entrypoints

```bash
composer sift <command>
composer skills <command>
php vendor/bin/sift <command>
php sift.phar <command>
```

All entrypoints render the same JSON payload for the same arguments.

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

Precedence is:

```text
command option > global option > config > default
```

## Built-ins

- `help`, `--help`, `-h`
- `version`, `--version`, `-V`
- `init`
- `validate`
- `tools list`, `tools ls`
- `run <tool> [args]`
- `<tool> [args]`

## Skills

- `skills list`, `skills ls`
- `skills add <source>`
- `skills add <source> --list`
- `skills find [query]`
- `skills init [name]`
- `skills remove <skill>`, `skills rm <skill>`
- `skills update [skill ...]`

`composer skills <args>` is the same command family without the `skills` prefix.

## History

- `history list`, `history ls`
- `history view <run_id>`
- `history <run_id>`
- `history view <run_id> summary|items|meta|artifacts|extra`
- `history remove <run_id ...>`, `history rm <run_id ...>`
- `history clear`

`--limit` and `--offset` are strict integers.
