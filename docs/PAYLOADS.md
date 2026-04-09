# Payloads

This document defines the structural contract of Sift's normalized result.

Use this file for:

- the shared top-level shape
- which fields are guaranteed
- common meta guarantees
- the per-tool matrix of required fields

If you need to understand rendering, `compact` / `normal` / `fuller`, `markdown` vs `json`, or `--raw`, use [OUTPUTS.md](OUTPUTS.md).

`sift` normalizes all tools to the same base shape:

```json
{
  "status": "passed|failed|error|changed",
  "summary": {},
  "items": [],
  "artifacts": [],
  "extra": {},
  "meta": {}
}
```

## Shared Shape

- `tool`: always present
- `status`: always present
- `summary`: always present
- `items`: always present
- `artifacts`: always present in the normalized shape, even when empty
- `extra`: always present in the normalized shape, even when empty
- `meta`: always present

## Common Meta Guarantees

Fields guaranteed for every tool after `ResultMetaStamper`:

- `meta.exit_code`
- `meta.duration`
- `meta.created_at`

Contextual meta fields may appear beyond those:

- `meta.command`
- `meta.filter`
- `meta.coverage`
- `meta.mode`
- `meta.dry_run`
- `meta.subcommand`

## Tool Matrix

| Tool | `summary` obrigatório | `items` obrigatório | `artifacts` | `extra` | Meta contextual |
| --- | --- | --- | --- | --- | --- |
| `phpunit` | `tests`, `passed`, `failures`, `errors`, `skipped` | `type`, `file`, `message`; `line` when available. `test` is kept in `fuller` | não | não | `command`, `filter`, `coverage`, `coverage_min` |
| `pest` | `tests`, `passed`, `failures`, `errors`, `skipped` | `type`, `file`, `message`; `line` when available. `test` is kept in `fuller` | não | não | `command`, `filter`, `coverage`, `coverage_min` |
| `paratest` | `tests`, `passed`, `failures`, `errors`, `skipped` | `type`, `file`, `message`; `line` when available. `test` is kept in `fuller` | não | não | `command`, `filter`, `coverage`, `coverage_min` |
| `phpstan` | `errors`, `files` | `file`, `message` | não | não | `command` |
| `phpcs` | `errors`, `warnings`, `fixable`, `files` | `type`, `file`, `message`; `line`, `column`, `rule` and `fixable` when available | não | não | `command` |
| `pint` | `files`, `fixers` | `file`, `fixers` | não | não | `command`, `mode` |
| `psalm` | `issues`, `files` | `type`, `message`; `rule`, `file`, `line` and `column` when available | não | não | `command` |
| `rector` | `changed_files`, `errors` | `type`, `file` for change items; `type`, `message` for error items; `line`, `caused_by` and `applied_rectors` when available | `file`, `diff`; `applied_rectors` when available | não | `command`, `dry_run` |
| `composer-audit` | `vulnerabilities`, `packages` | `package`, `severity`; advisory metadata when available | não | não | `command` |
| `composer` | `dependencies`, `licenses` for `licenses`; `packages`, `outdated`, `abandoned` for `show` and `outdated`; `vulnerabilities`, `packages` for `audit` | `package`, `licenses` for `licenses`; `package`, `version` for `show` and `outdated`, with update or replacement fields only when applicable; advisory metadata for `audit` only when available | não | `root_package` for `licenses`, when Composer exposes it | `command`, `subcommand`, `mode` |

## Notes

- Fields listed as required are guaranteed when the tool parse succeeds.
- Empty strings, `false` flags, and other default-value noise are omitted from tool payloads when possible.
- Rendered sizes may intentionally trim some item fields, such as omitting `test` from `normal` while keeping it in `fuller`.
- Extra fields inside `items`, `artifacts`, `extra`, or `meta` may appear without breaking the contract.
- Smaller rendered outputs may omit parts of this structure, but the internal normalized result still converges to the contract above.
