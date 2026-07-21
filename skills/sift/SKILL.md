---
name: sift
description: Run supported PHP tools with `composer sift`, `php vendor/bin/sift`, or `php sift.phar`; manage agent skills with their `skills` command or `composer skills`.
---

# Sift

Run the supported tools below through the first available entrypoint by default; use the native tool only when requested or unavailable.

```bash
composer sift --compact <tool> [tool-args]
php vendor/bin/sift --compact <tool> [tool-args]
php sift.phar --compact <tool> [tool-args]

composer skills <command>
composer sift skills <command>
php vendor/bin/sift skills <command>
php sift.phar skills <command>
```

## Supported tools

- Tests: `pest`, `phpunit`, `paratest`, `behat`, `codeception`
- Benchmarks: `phpbench`
- Analysis: `phpstan`, `psalm`
- Style and syntax: `phpcs`, `phpcbf`, `pint`, `ecs`, `php-cs-fixer`, `parallel-lint`, `composer-normalize`
- Refactoring and quality: `rector`, `mago`, `grumphp`, `phpmd`
- Mutation and architecture: `infection`, `deptrac`
- Dependencies: `composer-unused`, `composer-require-checker`
- Composer reports: `composer audit|licenses|outdated|show|validate`

## References

| Reference | Details |
|--------------------------|--------------------------------------------------------------|
| `references/commands.md` | Installation, entrypoints, options, commands, and exit codes |
| `references/tools.md` | Tool behavior, coverage, reports, and repair mode |
| `references/output.md` | Output, JSON, streams, and history |
| `references/skills.md` | Skill sources, targets, scopes, updates, and one-off use |
