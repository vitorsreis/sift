---
name: sift
description: Use when working in a PHP project that exposes Sift through composer sift, composer skills, vendor/bin/sift, bin/sift, global sift, or sift.phar, especially before running PHP quality tools or installing agent skills.
---

# Sift

Sift is the Composer-native command layer for PHP quality tools, compact tool output, run history, and agent skills.

## Mandatory Defaults

- If the project has `composer sift`, run supported PHP tools through `composer sift --compact`.
- If the task is about skills, use `composer skills`.
- If Sift is installed globally instead of in the project, use `sift <command>` and `sift skills <command>`.
- If a tool output is too short, inspect Sift history before rerunning the tool.
- Use `--json` only when you need a machine-readable payload.
- Use `--full` only after compact output and history sections are insufficient.
- Use `--raw` only when the user needs native tool output.
- Use `--no-color` only when ANSI output would pollute copied logs or snapshots.
- Use `composer skills` for skill management in a Sift project.
- Do not install Sift unless the user asks. If Sift is missing or broken, fall back to the native tool.

## Tool Commands

Use these forms first:

```bash
composer sift --compact pest
composer sift --compact phpunit
composer sift --compact paratest
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact phpcs src
composer sift --compact pint
composer sift --compact rector process --dry-run src
composer sift --compact composer audit
```

For coverage thresholds:

```bash
composer sift --compact pest --coverage --min=80
composer sift --compact phpunit --coverage --min=80
```

For PHPCBF repairs, require explicit user intent because it writes files:

```bash
composer sift --compact phpcbf --repair src
```

Use `composer sift tools list` to verify tool availability.

Global install for user-level access outside one project:

```bash
composer global config allow-plugins.vitorsreis/sift true
composer global require vitorsreis/sift
```

## History

When Sift prints a `run_id`, inspect stored sections instead of rerunning noisy tools:

```bash
composer sift history list
composer sift history view <run_id> summary
composer sift history view <run_id> items
composer sift history view <run_id> meta
```

## Skills

```bash
composer skills find
composer skills add owner/repo --list
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills add vitorsreis/sift --skill sift --agent=codex --yes
composer skills list
```

`composer skills add` targets project skill directories by default and accepts multi-value flags such as `--agent codex cursor` and `--skill pr-review commit`. In terminal mode it can prompt for agents, preselect existing target folders, then prompt for Project or Global scope when selected targets support global installs. In non-interactive mode, use `--yes --agent=<target>` or `--all`; add `--global` only for user-level installs. Use `--subagent <name>` for Eve subagents.

`help`, `version`, `tools list`, and the root `composer skills` help always render terminal output. `tools list` streams results as version checks finish. `composer skills find` without a query opens the interactive Skills search in terminal mode.

`composer skills use <source>@<skill>` prints a one-off prompt without installing. `composer skills upgrade` aliases `composer skills update`; named updates check project and global installs unless `--project` or `--global` is passed.

## Safety

- Keep Composer through Sift read-only: `audit`, `licenses`, `outdated`, `show`, `validate`.
- Keep Rector and formatters in dry-run/check mode unless the user explicitly asks for write mode.
- Run PHPCBF only with `--repair` and only after explicit repair intent.
- Do not bypass blocked arguments unless the user changes config.
- Do not parse native text when Sift reports that machine output is unsupported.
- Policies apply to normal runs and `--raw`.

## References

- `references/commands.md`: CLI grammar and supported commands. Read when exact syntax is needed.
- `references/workflows.md`: task recipes. Read when choosing how to run checks, inspect history, or manage skills.
- `references/architecture.md`: payload, config, history, and module contracts. Read when changing Sift internals.
