---
name: sift
description: Use Sift when a PHP project exposes composer sift, composer skills, vendor/bin/sift, bin/sift, or sift.phar and the task involves agent skills, tests, static analysis, formatters, Rector dry-runs, PHPCS/Pint/Mago, PHPUnit/Pest/Paratest, PHPStan/Psalm, or read-only Composer checks.
---

# Sift

## Core Rule

Use Sift as the default interface for supported PHP tools and project agent setup.

Prefer compact output first:

```bash
composer sift --compact <tool> [tool-args]
```

Fallbacks:

```bash
php vendor/bin/sift --compact <tool> [tool-args]
php sift.phar --compact <tool> [tool-args]
```

## Skills

```bash
composer skills add sift --agent=codex --yes
composer skills add owner/repo --list
composer skills add owner/repo --skill review --agent=generic --yes
composer skills list
composer skills update review --yes
composer skills remove review --yes
```

## History

```bash
composer sift history list
composer sift history view <run_id>
composer sift history view <run_id> items
```

## Escalation

```text
--compact
history view <run_id> <section>
--full
--raw
native tool
```

Use `--raw` only when native output is required. Policies still apply.

## Safety

- Composer is read-only through Sift: `audit`, `licenses`, `outdated`, `show`.
- Rector must stay dry-run unless explicit repair support is implemented and requested.
- Pint and php-cs-fixer default to check/dry-run mode.
- Mago format must use safe check/dry-run/stdin modes.
