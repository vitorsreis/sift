# Contributing

## Development Principles

- Keep changes small and reviewable.
- Use TDD for behavior changes: write the failing Pest test first, watch it fail, then implement.
- Keep docs, schema, tests, and runtime contracts aligned in the same change.
- Prefer explicit value objects and small services over large orchestration classes.
- Do not add compatibility with Sift v1 unless a v2 plan explicitly says so.

## Local Setup

```bash
composer install
composer validate --strict
composer test
composer quality
```

`composer.lock` is intentionally not committed while Sift is treated as a Composer library/plugin.

## Quality Commands

```bash
composer test
composer analyse
composer format
composer rector
composer quality
```

`composer quality` must run Pest, PHPStan, Pint in test mode, and Rector dry-run.

## Tests

- Unit tests cover parser, config, policies, adapters, filesystem helpers, output, history, and skills.
- Integration tests use fake PHP binaries instead of shell scripts so Windows and Linux behave the same.
- End-to-end tests with real external tools are optional and must skip clearly when a binary is missing.
- Tests should exercise public behavior, not private helpers.

## Documentation

Update documentation when changing:

- command names or flags
- payload fields
- error codes or exit codes
- `sift.json` schema
- supported tools
- skill targets or managed metadata
- PHAR behavior

## Pull Requests

Include:

- user-facing change summary
- tests run
- config, schema, docs, or payload changes
- intentional follow-up work, only when deliberately deferred

## Commit Style

Use Conventional Commits:

```text
feat(console): add declarative cli parser
fix(history): keep clear inside custom path
docs(skills): document managed block metadata
```

Do not commit generated PHAR files, `vendor/`, coverage, cache directories, or local configs.
