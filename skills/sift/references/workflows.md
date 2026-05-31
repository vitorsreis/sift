# Workflows

## First Run

```bash
composer sift init
composer sift validate
composer sift tools list
```

## Test

```bash
composer sift --compact pest
composer sift --compact phpunit --filter=Name
composer sift --compact paratest
```

## Static Analysis

```bash
composer sift --compact phpstan analyse
composer sift --compact psalm
composer sift --compact mago analyze src
```

## Formatting Checks

```bash
composer sift --compact pint
composer sift --compact php-cs-fixer fix --dry-run
composer sift --compact mago format --check
```

## Investigate Detail

```bash
composer sift history list
composer sift history view <run_id> summary
composer sift history view <run_id> items
composer sift history view <run_id> meta
```
