# Tools

Use this reference for adapter behavior and tool-specific arguments.

## Adapter behavior

| Tool                       | Sift behavior                                                             |
|----------------------------|---------------------------------------------------------------------------|
| `pest`                     | JUnit output, coverage parsing, native `--min` support                    |
| `phpunit`                  | JUnit output, coverage parsing, Sift-level `--min` support                |
| `paratest`                 | JUnit output and coverage parsing                                         |
| `behat`                    | JSON scenario output                                                      |
| `codeception`              | JUnit output, coverage parsing, Sift-level `--min` support                |
| `phpbench`                 | XML measurements, assertion failures, and execution errors                |
| `phpstan`                  | JSON analysis output                                                      |
| `psalm`                    | JSON analysis output                                                      |
| `phpcs`                    | JSON report                                                               |
| `phpcbf`                   | Repair-only normalized report                                             |
| `pint`                     | JSON test-mode report                                                     |
| `ecs`                      | JSON findings; write mode requires `--repair`                             |
| `php-cs-fixer`             | JSON dry-run report                                                       |
| `rector`                   | JSON dry-run report                                                       |
| `mago`                     | Normalized lint, analyze, guard, and format results                       |
| `grumphp`                  | Normalized task results                                                   |
| `infection`                | JSON mutation report                                                      |
| `deptrac`                  | JSON architecture report                                                  |
| `phpmd`                    | JSON report                                                               |
| `composer-normalize`       | Dry-run diff; write mode requires `--repair`                              |
| `composer-unused`          | JSON dependency report                                                    |
| `composer-require-checker` | JSON dependency report                                                    |
| `parallel-lint`            | JSON lint report                                                          |
| `composer`                 | Read-only `audit`, `licenses`, `outdated`, `show`, and `validate` reports |

Sift adds supported non-interactive, no-progress, and machine-readable flags before execution. Pass tool arguments after
the tool name.

## Coverage

```bash
composer sift --compact pest --coverage --min=80
composer sift --compact phpunit --coverage --min=80
composer sift --compact codeception --coverage --min=80
```

Coverage results expose `summary.coverage_percent`. Without `--min`, coverage items contain every covered file, ordered
from lowest coverage. With `--min`, items contain only files below the threshold.

Pest handles `--min` natively. Sift removes `--min` before invoking PHPUnit or Codeception and evaluates the generated
Clover report itself.

If a test runner exits before its generated report exists, read the actionable error from `items[].message` and native
output from `extra.stdout` or `extra.stderr`.

## Repair mode

Sift blocks mutating modes unless the command expresses repair intent. Policies also apply with `--raw`.

```bash
composer sift --compact phpcbf --repair src
composer sift --compact ecs --repair src
composer sift --compact composer-normalize --repair
```

Without the required `--repair`, Sift exits with code `3` before starting the tool.
