# Contributing

## Workflow

1. Start from a clean branch.
2. Keep changes focused and small.
3. Add or update Pest coverage for behavior changes.
4. Run the local checks before opening a pull request.

## Local Checks

```bash
composer lint
composer test
composer build:phar
```

## Pull Requests

- explain the user-facing impact
- call out config or payload changes
- include follow-up tasks if the implementation is intentionally partial

## Style

- PSR-12
- ASCII by default unless the file already requires Unicode
- prefer structured tool output over text parsing whenever the tool supports it
