# Tools

`tools list` reports every supported adapter, not only installed binaries.

It always renders terminal output and ignores `--json`. Version checks run in bounded parallel batches and each result is printed as soon as it is available, so order may vary. Use `output.colored=false` or `--no-color` for plain status text.

Terminal status:

- `OK`: installed and enabled
- `NO`: missing or disabled, with an install hint when available

## Supported Adapters

| Tool | Safe default |
| --- | --- |
| `phpunit` | JUnit output with `--no-output --colors=never`, coverage parsing, and Sift-level `--min` thresholds |
| `pest` | JUnit output with `--no-output --colors=never` and coverage parsing |
| `paratest` | JUnit output with `--no-progress --colors=never` and coverage parsing |
| `behat` | JSON scenario output with no colors or interaction |
| `codeception` | Silent JUnit output with no colors or interaction, coverage parsing, and Sift-level `--min` thresholds |
| `phpbench` | Silent XML measurements with no ANSI or interaction, assertion failures, and execution errors |
| `phpstan` | `analyse --error-format=json --no-progress --no-ansi --no-interaction` |
| `psalm` | `--output-format=json --no-progress --monochrome` |
| `phpcs` | JSON report, quiet, no colors |
| `phpcbf` | repair-only with `--repair`, quiet, no colors |
| `rector` | `process --dry-run --output-format=json --no-progress-bar --no-ansi` |
| `pint` | `--test --format=json --no-ansi --no-interaction` |
| `ecs` | JSON style findings without a progress bar; fixes require explicit `--repair` |
| `mago` | safe lint/analyze/guard/format modes |
| `grumphp` | `run --no-ansi --no-interaction` task results |
| `infection` | JSON logger with no progress, ANSI, or interaction |
| `deptrac` | JSON analyse output with no progress, ANSI, or interaction |
| `php-cs-fixer` | `fix --dry-run --format=json --show-progress=none --no-ansi --no-interaction` |
| `phpmd` | JSON report |
| `composer-normalize` | Non-interactive `normalize --dry-run --diff` without progress or ANSI; writes require explicit `--repair` |
| `composer-unused` | JSON output without progress, ANSI, or interaction |
| `composer-require-checker` | Non-interactive `check --output=json` without ANSI |
| `parallel-lint` | JSON output without progress or colors |
| `composer` | Non-interactive, no-progress, no-ANSI read-only `audit`, `licenses`, `outdated`, `show`, `validate` |

## Test Runners

Sift injects JUnit output for `phpunit`, `pest`, `paratest`, and `codeception` when the command does not already request it. Coverage runs also inject Clover output when needed. Behat uses its JSON formatter to normalize scenarios, and PHPBench uses its XML dump to normalize benchmark measurements.

Coverage payloads include `summary.coverage_percent`. Without `--min`, coverage `items` list every covered file sorted by lowest coverage first. With `--min`, coverage `items` list only files below the threshold and `summary` includes `coverage_min` and `coverage_files_below_min`.

Pest supports `--min` natively. PHPUnit and Codeception do not, so Sift treats `--min` / `--min=<percent>` as its own threshold option for those runners, removes it before execution, and evaluates the generated Clover report after the run.

If the native runner exits before those reports are written, for example because of an unknown PHPUnit option, Sift returns a normalized `error` payload with the actionable native error line in `items[].message` and preserves raw stdout/stderr in `extra`.

## Safety

Policies run before process execution and before raw mode. A blocked command exits with code `3`.

`phpcbf` modifies files and has no dry-run mode, so Sift only runs it when `--repair` is explicit. Without `--repair`, Sift exits before process execution with code `3`.

Composer Normalize and ECS also require `--repair` before Sift permits write mode.

```bash
composer sift phpcbf --repair src
```

PHPCBF output is normalized from its text summary:

- `changed`: files were fixed and no violations remain.
- `failed`: PHPCBF fixed what it could, but remaining violations were reported.
- `passed`: no violations were found.

Machine-readable tool output is required outside `--raw`; native text output is not parsed as a fallback when the adapter contract requires JSON or XML. Sift also forces supported no-progress, no-color, no-ANSI, silent, and non-interactive controls so the parser receives the cleanest available result. `--raw` leaves those native presentation controls to the caller while safety policies remain active.
