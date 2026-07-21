# Commands

Use this file for exact syntax. Keep workflow decisions in `workflows.md`.

## Entrypoints

```bash
composer sift <command>
composer skills [command]
php vendor/bin/sift <command>
sift <command>
php sift.phar <command>
```

Global install:

```bash
composer global config allow-plugins.vitorsreis/sift true
composer global require vitorsreis/sift
```

Composer's global `vendor/bin` directory must be in `PATH` for `sift <command>` to work.

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

- `pest`, `phpunit`, `paratest`, `behat`, `codeception`
- `phpbench`
- `phpstan`, `psalm`, `phpcs`, `phpcbf`, `parallel-lint`
- `pint`, `ecs`, `php-cs-fixer`, `rector`, `mago`, `grumphp`
- `infection`, `deptrac`, `phpmd`
- `composer-normalize`, `composer-unused`, `composer-require-checker`
- `composer audit`, `composer licenses`, `composer outdated`, `composer show`, `composer validate`

For `pest`, `phpunit`, `paratest`, and `codeception`, Sift injects JUnit and coverage reports when needed. Coverage output includes per-file coverage items when no `--min` threshold is provided; with `--min`, items are limited to files below the threshold. For `phpunit` and `codeception`, Sift treats `--min` as its own threshold option and removes it before execution. Behat uses JSON scenario reports, while PHPBench uses XML benchmark dumps. If a runner exits before generated reports are written, inspect `items[].message` for the native error and `extra.stdout` / `extra.stderr` for the raw output.

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
composer skills add vitorsreis/sift --skill sift --agent=standard --yes
composer skills add owner/repo --list
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills add owner/repo --skill pr-review commit --agent standard cursor --yes
composer skills add owner/repo --skill <name> --agent=<target> --global --yes
composer skills add owner/repo --subagent <eve-subagent> --yes
composer skills use owner/repo@skill
composer skills find [query]
composer skills init [name] [--yes]
composer skills update|upgrade [name ...] [--agent=<target>] [--project|--global] --yes
composer skills remove [name ...] [--agent=<target>] [--global] --yes
```

`composer skills` renders the bannered skills help. `composer skills find` without a query opens interactive typeahead search in terminal mode.
`composer skills add` can prompt for agents and Project/Global scope in terminal mode. In CI, use `--yes --agent=<target>` or `--all`; use `--global` only for user-level skill directories. Skills commands accept multi-value flags, including `--agent standard cursor` and `--skill pr-review commit`.

`composer skills use` prints a prompt for one selected skill without installing. `composer skills update` asks for Project/Global/Both in terminal mode; with `--yes`, it updates project skills when present and otherwise falls back to global.
