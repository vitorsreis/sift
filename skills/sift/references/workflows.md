# Workflows

## New Project Setup

```bash
composer sift init
composer sift validate
composer skills add vitorsreis/sift --skill sift
composer sift tools list
```

## Failing Test Investigation

```bash
composer sift --compact --pretty pest --filter=CheckoutTest
composer sift history list
composer sift history view <run_id> items
composer sift history view <run_id> meta
```

Use `--full` only when the history sections omit required detail.

## Static Analysis

```bash
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact mago analyze src
```

## Formatting And Refactoring Checks

```bash
composer sift --compact pint
composer sift --compact php-cs-fixer fix --dry-run
composer sift --compact rector process --dry-run src
composer sift --compact mago format --check src
```

Do not switch to write mode without explicit user intent.

## Dependency Checks

```bash
composer sift --compact composer audit
composer sift --compact composer outdated
composer sift --compact composer-unused
composer sift --compact composer-require-checker
```

## Skill Review Before Install

```bash
composer skills add owner/repo --list
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills list
```
