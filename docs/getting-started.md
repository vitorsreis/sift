# Getting Started

## Install

```bash
composer require --dev vitorsreis/sift
composer config allow-plugins.vitorsreis/sift true
```

## Initialize

```bash
composer sift init -y
```

`init` creates a minimal `sift.json` and can install the bundled `sift` skill. In non-TTY or CI, it uses safe defaults and does not install skills unless `--yes`, `--skill`, or an explicit equivalent is used.

## Run Tools

```bash
composer sift --compact pest
composer sift --compact phpstan analyse src
composer sift --compact pint
composer sift --compact composer audit
```

Output is terminal text by default. Use `--json` when you need the normalized payload, and `--full` only when compact output does not include enough information.

```bash
composer sift --json --compact pest
```

## Inspect History

```bash
composer sift history list
composer sift history view <run_id>
composer sift history view <run_id> items
```

History is disabled in `--raw` mode.
History records are flat JSON files keyed by sortable `run_id`.

## Install Agent Instructions

```bash
composer skills add vitorsreis/sift --skill sift --agent=codex --yes
composer skills list
```

Use `skills add <source> --list` to preview external repositories before installing.
