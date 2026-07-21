# Commands

Use this reference for exact Sift CLI syntax.

## Installation

Project installation:

```bash
composer config allow-plugins.vitorsreis/sift true
composer require --dev vitorsreis/sift
composer sift init -y
```

## Entrypoints

```bash
composer sift <command>
php vendor/bin/sift <command>
php sift.phar <command>

composer skills <command>
composer sift skills <command>
php vendor/bin/sift skills <command>
php sift.phar skills <command>
```

All entrypoints call the same application core.

## Global options

| Option                                | Purpose                                    |
|---------------------------------------|--------------------------------------------|
| `--compact`                           | Return only primary result fields.         |
| `--full`                              | Return the complete normalized result.     |
| `--json`, `--no-json`                 | Enable or disable normalized JSON output.  |
| `--pretty`, `--no-pretty`             | Enable or disable pretty JSON formatting.  |
| `--raw`                               | Pass through native output and exit code.  |
| `--show-process`, `--no-show-process` | Show or hide the executed process.         |
| `--no-color`                          | Disable ANSI styling for one command.      |
| `--debug`                             | Write diagnostics to `STDERR`.             |
| `--history`, `--no-history`           | Enable or disable history for one command. |
| `--config=<path>`, `-c <path>`        | Select a Sift configuration file.          |
| `-d <name=value>`                     | Set a runtime configuration value.         |

Precedence is `command option > global option > config > default`.

## Core commands

```bash
composer sift help
composer sift version
composer sift init [--force] [--yes] [--skill|--no-skill] [--config=<path>]
composer sift validate [--config=<path>]
composer sift tools list [--config=<path>]
composer sift run <tool> [tool-args]
composer sift <tool> [tool-args]
```

When passing Sift options through the `composer skills` script, use Composer's end-of-options marker:

```bash
composer skills -- --no-color
```

## Exit codes

- `0`: passed
- `1`: findings, failures, diffs, or expected changes
- `2`: operational error
- `3`: invalid usage, configuration, or blocked operation before execution
- `130`: interrupted
