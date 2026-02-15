# Commands

This document explains what each Sift command does and how runtime flags change behavior.

## Command Groups

Sift has two kinds of commands:

- reserved commands: handled directly by Sift
- wrapped tool commands: forwarded to a supported adapter such as `phpunit`, `pest`, `phpstan`, or `pint`

Reserved commands are:

- `help`
- `version`
- `init`
- `add`
- `list`
- `validate`
- `view`

Everything else is resolved through the tool registry.

## Global Runtime Options

These flags can be used before the command name:

- `--format=json|markdown`: selects the renderer
- `--size=compact|normal|fuller`: selects payload size
- `--pretty`: enables pretty output formatting
- `--no-pretty`: disables pretty output formatting
- `--raw`: bypasses normalization and returns native tool output directly
- `--show-process`: shows recent process output while a tool is running
- `--no-show-process`: suppresses live process output even if config enables it
- `--no-history`: disables history persistence for the current execution
- `--config=<path>`: overrides the config path for the current execution

## `sift help`

Prints the CLI surface exposed by the current build:

- usage lines
- reserved commands
- main runtime options

Useful when checking what a packaged PHAR or a particular release actually exposes.

## `sift version`

Prints the current Sift version only.

Useful for CI, support, or release verification.

## `sift init`

Creates `sift.json` with:

- the local schema reference
- output defaults
- history defaults
- detected project tools where available

Accepted command-level options:

- `--force`
- `--config=<path>`
- output flags such as `--format`, `--size`, and `--pretty`

## `sift add [tool]`

Registers a supported tool in the config and persists:

- `enabled`
- adapter `defaultArgs`
- detected `toolBinary`

### Explicit Mode

When `tool` is present:

```bash
sift add phpstan
```

Sift validates the tool name, checks whether it is installed, and writes or updates the corresponding `tools.<name>` config entry.

### Interactive Mode

When `tool` is omitted:

```bash
sift add
```

Sift inspects the project, lists detected supported tools, and accepts a selection by:

- number
- exact tool name

Interactive selection is only used to choose the tool; the resulting config write is the same as explicit mode.

## `sift list`

Inspects the current project and returns the state of every registered tool:

- tool name
- whether it is enabled by config
- whether it is installed
- resolved path when detected
- configured binary override when present

Useful to compare what the project has installed versus what `sift.json` currently enables.

## `sift validate`

Loads the active config, validates it against Sift rules, and reports normalized tool settings.

Useful to catch:

- invalid JSON
- schema mismatches
- invalid history settings
- malformed tool sections

## `sift view`

Reads stored normalized runs from history.

### Listing Runs

```bash
sift view list
```

Accepted options:

- `--limit=<n>`
- `--offset=<n>`
- `--config=<path>`
- renderer flags such as `--format` and `--pretty`

### Viewing a Run

```bash
sift view <run_id>
sift view <run_id> summary
sift view <run_id> items
sift view <run_id> meta
sift view <run_id> artifacts
sift view <run_id> extra
```

Scopes allow reading only the part of a stored payload that matters for the current task.

### Clearing History

```bash
sift view --clear
```

Clears the configured history directory. This command does not accept a run id or scope.

## Wrapped Tool Execution

The general form is:

```bash
sift <tool> [tool-args]
```

Examples:

```bash
sift phpstan analyse src
sift pest --testsuite=Integration
sift rector process --dry-run src
sift composer-audit
```

At runtime, Sift:

1. resolves config and runtime overrides
2. merges `defaultArgs` when no CLI args were supplied
3. runs policies such as disabled-tool and blocked-argument checks
4. prepares a native command through the selected adapter
5. runs the tool
6. parses native output into the normalized Sift shape
7. persists history unless disabled

## `--raw`

`--raw` is only meaningful for wrapped tool execution.

Instead of returning normalized output, Sift:

- skips adapter parsing
- skips payload rendering
- skips history storage
- preserves the native process exit code
- returns native `stdout` and `stderr` directly

Useful when debugging parser failures or comparing native versus normalized output.

## `--show-process`

`--show-process` only affects wrapped tool execution.

When enabled, Sift shows a live tail of recent process output while the tool runs. This is useful for long-running tasks such as:

- test suites
- static analysis
- formatter runs

The final rendered payload is still produced after the process exits.
