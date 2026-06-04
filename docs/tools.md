# Tools

`tools list` reports every supported adapter, not only installed binaries.

It always renders terminal output and ignores `--json`. Version checks run in bounded parallel batches and each result is printed as soon as it is available, so order may vary.

Terminal status:

- `OK`: installed and enabled
- `NO`: missing or disabled, with an install hint when available

## Supported Adapters

| Tool | Safe default |
| --- | --- |
| `phpunit` | JUnit output injected when missing |
| `pest` | JUnit output and coverage parsing |
| `paratest` | JUnit output |
| `phpstan` | `analyse --error-format=json --no-progress` |
| `psalm` | JSON output |
| `phpcs` | JSON report, quiet, no colors |
| `rector` | `process --dry-run --output-format=json` |
| `pint` | `--test --format=json` |
| `mago` | safe lint/analyze/guard/format modes |
| `infection` | JSON logger |
| `deptrac` | JSON analyse output only |
| `php-cs-fixer` | `fix --dry-run --format=json` |
| `phpmd` | JSON report |
| `composer-unused` | JSON output |
| `composer-require-checker` | `check --format=json` |
| `parallel-lint` | JSON output |
| `composer` | read-only `audit`, `licenses`, `outdated`, `show` |

## Safety

Policies run before process execution and before raw mode. A blocked command exits with code `3`.

Machine-readable tool output is required outside `--raw`; native text output is not parsed as a fallback when the adapter contract requires JSON or XML.
