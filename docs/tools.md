# Tools

`tools list` reports every supported adapter, not only installed binaries.

It always renders terminal output and ignores `--json`. Version checks run in bounded parallel batches and each result is printed as soon as it is available, so order may vary. Use `output.colored=false` or `--no-color` for plain status text.

Terminal status:

- `OK`: installed and enabled
- `NO`: missing or disabled, with an install hint when available

## Supported Adapters

| Tool | Safe default |
| --- | --- |
| `phpunit` | JUnit output, coverage parsing, and Sift-level `--min` thresholds |
| `pest` | JUnit output and coverage parsing |
| `paratest` | JUnit output and coverage parsing |
| `phpstan` | `analyse --error-format=json --no-progress` |
| `psalm` | JSON output |
| `phpcs` | JSON report, quiet, no colors |
| `phpcbf` | repair-only with `--repair`, quiet, no colors |
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
| `composer` | read-only `audit`, `licenses`, `outdated`, `show`, `validate` |

## Test Runners

Sift injects JUnit output for `phpunit`, `pest`, and `paratest` when the command does not already request it. Coverage runs also inject Clover output when needed.

Coverage payloads include `summary.coverage_percent`. Without `--min`, coverage `items` list every covered file sorted by lowest coverage first. With `--min`, coverage `items` list only files below the threshold and `summary` includes `coverage_min` and `coverage_files_below_min`.

Pest supports `--min` natively. PHPUnit does not, so Sift treats `--min` / `--min=<percent>` as its own threshold option for `phpunit`, removes it before executing PHPUnit, and evaluates the generated Clover report after the run.

If the native runner exits before those reports are written, for example because of an unknown PHPUnit option, Sift returns a normalized `error` payload with the actionable native error line in `items[].message` and preserves raw stdout/stderr in `extra`.

## Safety

Policies run before process execution and before raw mode. A blocked command exits with code `3`.

`phpcbf` modifies files and has no dry-run mode, so Sift only runs it when `--repair` is explicit. Without `--repair`, Sift exits before process execution with code `3`.

```bash
composer sift phpcbf --repair src
```

PHPCBF output is normalized from its text summary:

- `changed`: files were fixed and no violations remain.
- `failed`: PHPCBF fixed what it could, but remaining violations were reported.
- `passed`: no violations were found.

Machine-readable tool output is required outside `--raw`; native text output is not parsed as a fallback when the adapter contract requires JSON or XML.
