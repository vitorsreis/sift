# Commands

Use this file for exact syntax. Keep workflow decisions in `workflows.md`.

## Entrypoints

```bash
composer sift <command>
composer skills [command]
php vendor/bin/sift <command>
php sift.phar <command>
```

## Global Options

```text
--compact
--full
--pretty | --no-pretty
--json | --no-json
--raw
--show-process | --no-show-process
--no-color
--debug
--history | --no-history
--config=<path>
```

## Sift Commands

```bash
composer sift help
composer sift version
composer sift init [--force] [--yes] [--skill|--no-skill] [--config=<path>]
composer sift validate [--config=<path>]
composer sift tools list [--config=<path>]
```

`help`, `version`, `tools list`, and the root `composer skills` help always render terminal output and ignore `--json`. `tools list` streams each result as version checks finish. Use `output.colored=false` or `--no-color` for plain terminal output.

## Tool Runs

Pattern:

```bash
composer sift --compact <tool> [tool-args]
composer sift run <tool> [tool-args]
composer sift --json composer validate
```

Supported tool names:

- `pest`, `phpunit`, `paratest`
- `phpstan`, `psalm`, `phpcs`, `parallel-lint`
- `pint`, `php-cs-fixer`, `rector`, `mago`
- `infection`, `deptrac`, `phpmd`
- `composer-unused`, `composer-require-checker`
- `composer audit`, `composer licenses`, `composer outdated`, `composer show`, `composer validate`

## History Commands

```bash
composer sift history list [--limit=<n>] [--offset=<n>]
composer sift history view <run_id> [summary|items|meta|artifacts|extra]
composer sift history <run_id>
composer sift history remove <run_id>...
composer sift history clear
```

`history <run_id>` only aliases `history view` when the token matches a valid run id.

History records are flat JSON files keyed by sortable `run_id`.

## Skill Commands

```bash
composer skills list
composer skills add vitorsreis/sift --skill sift --agent=codex --yes
composer skills add owner/repo --list
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills find [query]
composer skills init [name] [--yes]
composer skills update [name ...] --yes
composer skills remove [name ...] --yes
```

`composer skills` renders the bannered skills help. `composer skills find` without a query opens interactive typeahead search in terminal mode.
