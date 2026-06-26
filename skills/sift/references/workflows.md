# Workflows

Use this file for task recipes. Use `commands.md` for full syntax.

## New Project

```bash
composer sift init
composer sift validate
composer skills add vitorsreis/sift --skill sift --agent=standard --yes
composer sift tools list
```

If running non-interactively, pass `--no-skill` or `--yes` to `init`.

## Global Install

```bash
composer global config allow-plugins.vitorsreis/sift true
composer global require vitorsreis/sift
```

## Failing Test

```bash
composer sift --compact pest --filter=CheckoutTest
composer sift history list
composer sift history view <run_id> items
composer sift history view <run_id> meta
```

Use `--full` only when the history sections omit required detail.
Use `--json` only when another program needs structured output.
Use `output.colored=false` for plain terminal output by default, or `--no-color` when one copied command must not include ANSI styling.

## Static Analysis And Quality

```bash
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact mago analyze src
composer sift --compact phpcs src
composer sift --compact pint
composer sift --compact php-cs-fixer fix --dry-run
composer sift --compact rector process --dry-run src
composer sift --compact mago format --check src
```

Do not switch to write mode without explicit user intent. PHPCBF has no dry-run mode, so run it only when the user explicitly wants repairs:

```bash
composer sift --compact phpcbf --repair src
```

## Dependencies

```bash
composer sift --compact composer audit
composer sift --compact composer outdated
composer sift --compact composer-unused
composer sift --compact composer-require-checker
```

Keep Composer actions read-only through Sift.

## Skill Install

```bash
composer skills find
composer skills add owner/repo --list
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills add owner/repo --skill pr-review commit --agent standard cursor --yes
composer skills list
```

Rules:

- Preview external sources with `--list` before install when possible.
- Use `composer skills find` without a query for interactive typeahead search in terminal mode.
- Mutating commands in non-TTY or CI need `--yes --agent=<target>` or `--all`.
- Skill installs target project directories by default. Add `--global` to use each target's user-level skills directory.
- Interactive `skills add` puts `standard` first for `.agents/skills` with Cursor, Gemini CLI, GitHub Copilot, OpenCode, Antigravity, Amp, Replit, and the remaining target count in the hint, keeps `generic` unselected for `AGENTS.md`, preselects existing target folders, and prompts for Project or Global scope when the selected target supports it.
- Use `composer skills use owner/repo@skill` when the user wants a one-off prompt without installation.
- Use `--subagent <name>` for Eve subagent installs and `--subagent root` for Eve's root `agent/skills`.
- `composer skills upgrade` aliases `composer skills update`; named updates check project and global installs unless a scope flag is passed.
- `--all` means all selected skills and all write-capable targets in the selected project/global scope.
- Directory targets use `.sift-skill.json`; the `generic` compatibility target uses managed blocks in `AGENTS.md`.
