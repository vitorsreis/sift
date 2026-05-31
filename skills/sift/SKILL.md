---
name: sift
description: Use Sift in PHP projects that expose composer sift, composer skills, vendor/bin/sift, bin/sift, or sift.phar. Covers PHP test runners, static analysis, linting, formatting checks, Rector dry-runs, dependency checks, structured history, and agent skill installation.
---

# Sift

## When To Use

Use Sift before calling native PHP tools directly when the task needs:

- PHPUnit, Pest, or Paratest.
- PHPStan, Psalm, PHPCS, Mago, or parallel-lint.
- Pint, php-cs-fixer, or Rector in safe check/dry-run mode.
- Composer `audit`, `licenses`, `outdated`, or `show`.
- composer-unused or composer-require-checker.
- Installing, listing, updating, finding, initializing, or removing agent skills.
- Reading previous run history in compact structured form.

Do not install Sift unless the user asks. If Sift is unavailable, fall back to the native tool.

## Entrypoint Order

Prefer the entrypoint already used by the project:

```bash
composer sift <command>
composer skills <command>
php vendor/bin/sift <command>
php sift.phar <command>
```

For tool runs, start compact:

```bash
composer sift --compact <tool> [tool-args]
```

## Core Workflow

1. Run the narrow Sift command with `--compact`.
2. If the compact payload is enough, stop.
3. If more detail is needed, inspect history by `run_id`.
4. Escalate to `--full` only when history sections are insufficient.
5. Use `--raw` only when native output is required.
6. Call the native tool directly only when Sift lacks the adapter or Sift itself is broken.

Escalation:

```text
--compact
history view <run_id> summary|items|meta|artifacts|extra
--full
--raw
native tool
```

## Tool Commands

```bash
composer sift --compact pest
composer sift --compact phpunit --filter=CheckoutTest
composer sift --compact paratest
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact phpcs
composer sift --compact rector process --dry-run src
composer sift --compact pint
composer sift --compact mago lint
composer sift --compact mago analyze src
composer sift --compact mago format --check src
composer sift --compact infection
composer sift --compact deptrac analyse
composer sift --compact php-cs-fixer fix --dry-run
composer sift --compact phpmd . json cleancode,codesize,controversial,design,naming,unusedcode
composer sift --compact composer-unused
composer sift --compact composer-require-checker
composer sift --compact parallel-lint
composer sift --compact composer audit
composer sift --compact composer licenses
composer sift --compact composer outdated
composer sift --compact composer show
```

## History

```bash
composer sift history list
composer sift history view <run_id>
composer sift history view <run_id> summary
composer sift history view <run_id> items
composer sift history view <run_id> meta
composer sift history view <run_id> artifacts
composer sift history view <run_id> extra
```

History is not written in `--raw` mode.

## Skills

Use `composer skills` for agent instruction management:

```bash
composer skills list
composer skills add sift --agent=codex --yes
composer skills add owner/repo --list
composer skills add owner/repo --skill review --agent=generic --yes
composer skills find review
composer skills init my-skill
composer skills update review --yes
composer skills remove review --yes
```

Rules:

- Preview external sources with `--list` before installing when possible.
- Mutating skill commands in non-TTY or CI need `--yes`.
- `--all` means all selected skills and all write-capable targets.
- Targets with unstable contracts are recognized but should not be forced.
- Managed blocks and `.sift-skill.json` metadata preserve manual content around Sift-managed areas.

## Config

`sift.json` is v2-only and uses `$schema`. The schema URL is pinned to the installed Sift version:

```php
"https://raw.githubusercontent.com/vitorsreis/sift/v" . Sift::VERSION . "/resources/schema.json"
```

Validate config before relying on project defaults:

```bash
composer sift validate
```

## Safety Rules

- Policies run before normal execution and before `--raw`.
- Composer through Sift is read-only: `audit`, `licenses`, `outdated`, `show`.
- Rector must be dry-run unless a future explicit repair flow allows mutation.
- Pint and php-cs-fixer default to check/dry-run mode.
- Mago format must use `--check`, `--dry-run`, or `--stdin-input`.
- Do not bypass blocked arguments unless the user explicitly changes config.
- Do not parse native text output when Sift reports that machine output is unsupported.

## References

- `references/commands.md`: command examples.
- `references/workflows.md`: common work patterns.
- `references/architecture.md`: modules and payload rules.
