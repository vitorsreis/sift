# CLI Reference

## Entrypoints

```bash
composer sift <command>
composer skills [command]
php vendor/bin/sift <command>
php sift.phar <command>
```

All entrypoints call the same application core and should return the same output for the same arguments.

## Global Options

- `--compact`
- `--full`
- `--pretty`, `-p`
- `--no-pretty`, `-P`
- `--json`
- `--no-json`
- `--raw`
- `--show-process`
- `--no-show-process`
- `--no-color`
- `--debug`
- `--history`
- `--no-history`
- `--config=<path>`, `-c <path>`
- `-d <name=value>`

Precedence:

```text
command option > global option > config > default
```

## Commands

- `help`, `--help`, `-h`
- `version`, `--version`, `-V`
- `init`
- `validate`
- `tools list`, `tools ls`
- `skills`
- `skills list`, `skills ls`
- `skills add <source>`, `skills a <source>`
- `skills use <source>[@<skill>]`
- `skills find [query]`
- `skills find [query] --owner <owner>`
- `skills init [name]`
- `skills remove [skill ...]`, `skills rm [skill ...]`
- `skills update [skill ...]`, `skills upgrade [skill ...]`
- `history list`, `history ls`
- `history view <run_id>`
- `history <run_id>`
- `history view <run_id> summary|items|meta|artifacts|extra`
- `history remove <run_id ...>`, `history rm <run_id ...>`
- `history clear`
- `run <tool> [args]`
- `<tool> [args]`

## Intentional V2 Breaks

Sift v2 keeps command names explicit. Ambiguous top-level aliases such as `add`, `list`, `view`, and `runs` are not supported. Tool registration through `tools add` is also not supported; tool configuration lives in `sift.json`.

## Streams

- Terminal result output goes to `STDOUT` by default and uses ANSI styling for status, labels, errors, and Skills CLI screens.
- `--json` writes normalized result payloads to `STDOUT`.
- `--no-json` forces terminal output when config selects JSON.
- `output.colored=false` disables ANSI styling by default, and `--no-color` disables it for one command without changing the output format.
- Sift errors go to `STDERR`.
- `--show-process` writes only to `STDERR`.
- `--debug` writes diagnostics to `STDERR` and keeps `STDOUT` unchanged.
- `--raw` passes through native stdout, stderr, and exit code.

`--compact`, default size, and `--full` control detail level in both terminal and JSON output. `--pretty` and `--no-pretty` affect JSON only. `--json` and `--raw` cannot be used together.

`help`, `version`, `tools list`, and the root `composer skills` help always render terminal output and ignore `--json`. Other `skills` commands prefer terminal output by default, even when config selects JSON, but still honor explicit `--json`.

`tools list` streams availability and version lines as checks finish, so order may vary.

`skills add`, `skills list`, `skills remove`, and `skills update` use project skill directories by default. Pass `--global` / `-g` to target the matching user-level skill directory. `skills update` also accepts `--project` / `-p`; named updates check both project and global unless a scope flag is passed.

In terminal mode, `skills add` can prompt for agents and scope. Existing target folders in the current scope are selected in the agent prompt, `codex` is selected when no target folder is detected, and the Project/Global scope prompt appears only when a selected target supports global installs. In non-interactive mode, use `--yes --agent=<target>` or `--all`; without `--global`, installs remain project-scoped.

Skills commands accept the public Skills CLI multi-value option style, for example `--agent codex cursor` and `--skill pr-review commit`. `skills add --subagent reviewer` targets Eve at `agent/subagents/reviewer/skills`; `--subagent root` targets Eve's root `agent/skills`.

`skills use` prints a prompt for one selected skill without installing it. It supports `<source>@<skill>` and `--skill <skill>` selection and returns terminal prompt text by default.

When a command is invoked through a Composer script rather than the installed Composer command, pass Composer's end-of-options marker before Sift options:

```bash
composer skills -- --no-color
```

Sift global options can be placed before the tool name:

```bash
composer sift --json composer validate
```

## Exit Codes

- `0`: passed
- `1`: findings, failures, diffs, or expected changes
- `2`: operational error
- `3`: usage, config, or policy error before execution
- `130`: detected interruption
