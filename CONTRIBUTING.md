# Contributing

## Workflow

1. Keep changes small and reviewable.
2. Write or update Pest coverage before implementation changes.
3. Keep docs, schema, and runtime contracts aligned.
4. Run focused checks while working.
5. Run `composer quality` before a milestone or pull request.

## Local Checks

```bash
composer test
composer analyse
composer format
composer rector
composer quality
```

## Pull Requests

- explain user-facing impact
- call out config, schema, payload, or command changes
- include tests run
- include follow-up tasks only when intentionally deferred

## Style

- PHP 8.3+
- strict types
- ASCII by default
- JSON output over text parsing when a tool supports it
