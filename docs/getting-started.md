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
composer sift --compact --pretty pest
composer sift --compact phpstan analyse src
composer sift --compact pint
composer sift --compact composer audit
```

Use `--full` only when the compact payload does not include enough information.

## Inspect History

```bash
composer sift history list
composer sift history view <run_id>
composer sift history view <run_id> items
```

History is disabled in `--raw` mode.

## Install Agent Instructions

```bash
composer skills add vitorsreis/sift --skill sift
composer skills list
```

Use `skills add <source> --list` to preview external repositories before installing.
