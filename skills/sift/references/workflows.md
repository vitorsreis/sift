# Workflows

Use this file for task recipes. Use `commands.md` for full syntax.

## New Project

```bash
composer sift init
composer sift validate
composer skills add vitorsreis/sift --skill sift --agent=codex --yes
composer sift tools list
```

If running non-interactively, pass `--no-skill` or `--yes` to `init`.

## Failing Test

```bash
composer sift --compact pest --filter=CheckoutTest
composer sift history list
composer sift history view <run_id> items
composer sift history view <run_id> meta
```

Use `--full` only when the history sections omit required detail.
Use `--json` only when another program needs structured output.

## Static Analysis And Quality

```bash
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact mago analyze src
composer sift --compact pint
composer sift --compact php-cs-fixer fix --dry-run
composer sift --compact rector process --dry-run src
composer sift --compact mago format --check src
```

Do not switch to write mode without explicit user intent.

## Dependencies

```bash
composer sift --compact composer audit
composer sift --compact composer outdated
composer sift --compact composer-unused
composer sift --compact composer-require-checker
```

Keep Composer actions read-only through Sift.

## Skill Install

```bash
composer skills add owner/repo --list
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills list
```

Rules:

- Preview external sources with `--list` before install when possible.
- Mutating commands in non-TTY or CI need `--yes`.
- `--all` means all selected skills and all write-capable targets.
- Managed blocks and `.sift-skill.json` preserve manual content around Sift-managed areas.
