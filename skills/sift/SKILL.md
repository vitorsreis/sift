---
name: sift
description: Use when working in a PHP project that has Sift available through composer sift, composer skills, vendor/bin/sift, bin/sift, or sift.phar.
---

# Sift

Sift is the project command layer for PHP tools and agent skills. Prefer it when the project already exposes Sift and the user has not asked for a native tool directly.

## Use

- Run PHP quality tools through `composer sift`.
- Manage agent skills through `composer skills`.
- Read structured results from Sift history before rerunning noisy tools.
- Prefer Sift history and compact output whenever possible to save context tokens.
- Use `--json` only when machine-readable output is needed.
- Use `output.colored=false` for plain terminal text by default, or `--no-color` for one command.

Do not install Sift unless the user asks. If Sift is unavailable or broken, use the native tool.

## Default Commands

Install the bundled Sift skill:

```bash
composer skills add vitorsreis/sift --skill sift --agent=codex --yes
```

Run a tool:

```bash
composer sift --compact pest
```

## Workflow

1. Use the project entrypoint. Prefer `composer sift` and `composer skills`.
2. Start tool runs with `--compact`.
3. If compact output is not enough, inspect `history view <run_id> <section>`.
4. Use `--json` only for structured automation or parsing.
5. Use `--no-json` to force terminal output when config selects JSON.
6. Use `--no-color` only when ANSI styling would pollute copied logs or snapshots for one command.
7. Use `--full` only when history sections are not enough.
8. Use `--raw` only when native output is required.

`help`, `version`, `tools list`, and the root `composer skills` help always render terminal output. `tools list` streams results as version checks finish. `composer skills find` without a query opens the interactive Skills search in terminal mode.

## Safety

- Keep Composer through Sift read-only: `audit`, `licenses`, `outdated`, `show`, `validate`.
- Keep Rector and formatters in dry-run/check mode unless the user explicitly asks for write mode.
- Do not bypass blocked arguments unless the user changes config.
- Do not parse native text when Sift reports that machine output is unsupported.
- Policies apply to normal runs and `--raw`.

## References

- `references/commands.md`: CLI grammar and supported commands. Read when exact syntax is needed.
- `references/workflows.md`: task recipes. Read when choosing how to run checks, inspect history, or manage skills.
- `references/architecture.md`: payload, config, history, and module contracts. Read when changing Sift internals.
