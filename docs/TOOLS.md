# Tools

## Contract

Each tool implementation uses `Sift\Contracts\ToolAdapterInterface` and is responsible for:

- exposing a stable tool name
- resolving an installation hint
- detecting execution context from CLI arguments
- preparing a native command with structured output flags
- parsing native output into the Sift payload shape

## Current Tools

### `phpunit`

- Parses JUnit XML
- Normalizes test summary, failures, and errors

### `pest`

- Reuses the PHPUnit-style JUnit flow
- Targets Pest's native test runner

### `paratest`

- Uses JUnit output for parallel test execution
- Normalizes the same failure and error shape used by `phpunit` and `pest`
- Preserves `filter` and coverage-related execution context in `meta`

### `phpstan`

- Forces JSON output
- Normalizes file diagnostics and totals

### `phpcs`

- Forces JSON output with quiet mode
- Normalizes warnings and errors as `items`

### `psalm`

- Forces JSON output with `--output-format=json`
- Disables progress noise with `--no-progress`
- Normalizes issue severity, rule, file, line, and column into `items`

### `rector`

- Supports `rector process --dry-run` flows
- Forces JSON output with `--output-format=json`
- Normalizes file diffs into `items` and `artifacts`
- Rejects write mode before execution, including `--raw`

### `pint`

- Forces JSON output
- Extracts files and fixers
- Tolerates noisy output around the JSON payload

### `composer-audit`

- Forces Composer audit JSON output
- Normalizes vulnerabilities by package and severity

### `composer`

- Supports only read-only Composer subcommands with structured output
- Forces JSON output for `audit`, `licenses`, `outdated`, and `show`
- Rejects unsupported or mutating subcommands before execution, including in `--raw`
- Normalizes license, package, and advisory data according to the active subcommand

## Adding a New Tool

1. Implement `ToolAdapterInterface`
2. Register the tool in `Application::registry()`
3. Add unit tests for parsing and context handling
4. Add at least one integration test through `bin/sift`
5. Document install hints and supported contexts here
