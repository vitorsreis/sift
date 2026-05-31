# Contributing

See the root [CONTRIBUTING.md](../CONTRIBUTING.md) for general workflow.

## Local Commands

```bash
composer validate --strict
composer test
composer analyse
composer format
composer rector
composer quality
```

## Test Strategy

- Write Pest tests before behavior changes.
- Use fake PHP binaries for integration tests.
- Keep real-tool end-to-end tests optional with clear skips.
- Avoid tests against private helpers.

## Documentation Rules

When changing behavior, update:

- relevant docs under `docs/`
- `resources/schema.json` when config changes
- `skills/sift/SKILL.md` when agent behavior changes
- `CHANGELOG.md` for release-visible changes
