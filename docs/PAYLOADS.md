# Payloads

`sift` normaliza todos os adapters para o mesmo shape base:

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

## Base Contract

- `status`: sempre presente, com estado final normalizado do run
- `summary`: sempre presente, agregado curto e estável para consumo de agentes
- `items`: sempre presente, lista de achados ou eventos relevantes
- `artifacts`: opcional por adapter; quando ausente no resultado bruto, o payload normalizado expõe lista vazia
- `extra`: opcional por adapter; quando ausente no resultado bruto, o payload normalizado expõe objeto vazio
- `meta`: sempre presente, com `exit_code`, `duration`, `created_at` e outros metadados contextuais quando disponíveis

## Common Meta Fields

Campos garantidos para todos os adapters depois do `ResultMetaStamper`:

- `meta.exit_code`
- `meta.duration`
- `meta.created_at`

Campos contextuais podem aparecer além desses:

- `meta.command`
- `meta.filter`
- `meta.coverage`
- `meta.mode`
- `meta.dry_run`

## Adapter Matrix

| Adapter | `summary` obrigatório | `items` obrigatório | `artifacts` | `extra` | Meta contextual |
| --- | --- | --- | --- | --- | --- |
| `phpunit` | `tests`, `passed`, `failures`, `errors`, `skipped` | `type`, `test`, `class`, `file`, `message` | não | não | `command`, `filter`, `coverage` |
| `pest` | `tests`, `passed`, `failures`, `errors`, `skipped` | `type`, `test`, `class`, `file`, `message` | não | não | `command`, `filter`, `coverage` |
| `paratest` | `tests`, `passed`, `failures`, `errors`, `skipped` | `type`, `test`, `class`, `file`, `message` | não | não | `command`, `filter`, `coverage` |
| `phpstan` | `errors`, `files` | `file`, `message` | não | não | `command` |
| `phpcs` | `errors`, `warnings`, `fixable`, `files` | `type`, `file`, `line`, `column`, `message`, `rule`, `fixable` | não | não | `command` |
| `pint` | `files`, `fixers` | `file`, `fixers` | não | não | `command`, `mode` |
| `psalm` | `issues`, `files` | `type`, `rule`, `message`, `file`, `line`, `column` | não | não | `command` |
| `rector` | `changed_files`, `errors` | `type`, `file`, `message` | `file`, `diff`, `applied_rectors` | não | `command`, `dry_run` |
| `composer-audit` | `vulnerabilities`, `packages` | `package`, `severity`, `advisory_id`, `title`, `cve`, `link` | não | não | `command` |

## Notes

- Campos listados como obrigatórios são garantidos pelo adapter quando o parse é bem-sucedido.
- Campos extras dentro de `items`, `artifacts` ou `meta` podem aparecer sem quebrar o contrato.
- Em `--size=compact` e `--size=normal`, parte do shape pode ser omitida só na renderização final; o `NormalizedResult` interno continua convergindo para o contrato acima.
