# Agent skills

Use this reference for Sift's agent-skill manager.

Use one of these entrypoints:

```bash
composer skills <command>
composer sift skills <command>
php vendor/bin/sift skills <command>
php sift.phar skills <command>
```

## Sources

Accept a bundled source, local skill directory, local repository, `owner/repo`, or GitHub HTTPS URL.

## Discover and preview

```bash
composer skills find [query]
composer skills find review --owner vitorsreis
composer skills add owner/repo --list
```

`find` without a query opens interactive search in a terminal. `add --list` discovers available skills without
installing them.

## Install

```bash
composer skills add owner/repo@skill
composer skills add owner/repo --skill <name> --agent=<target> --yes
composer skills add owner/repo --skill review docs --agent standard cursor --yes
composer skills add owner/repo --skill <name> --agent=<target> --global --yes
composer skills add owner/repo --subagent reviewer --yes
composer skills add owner/repo --all
```

Project scope is the default. Add `--global` for user-level target directories.

In a terminal, Sift can prompt for skills, target agents, scope, and confirmation. In CI or another non-interactive
environment, pass `--yes` with `--agent=<target>`, or use `--all`.

Multi-value and repeated `--skill` and `--agent` flags are supported. `--subagent <name>` targets an Eve subagent;
`--subagent root` targets Eve's root agent.

## Use without installing

```bash
composer skills use owner/repo@skill
composer skills use owner/repo --skill <name>
```

`skills use` copies the selected skill to a temporary support directory and prints a prompt. It does not write project
or global skill targets.

## List, update, and remove

```bash
composer skills list
composer skills update [name ...] --yes
composer skills update --project --yes
composer skills update --global --yes
composer skills remove [name ...] --agent=<target> --yes
composer skills remove [name ...] --agent=<target> --global --yes
```

`upgrade` aliases `update`. Named updates without a scope check both project and global installations. Unnamed
interactive updates ask for Project, Global, or Both.

## Common targets

| Target           | Project path                 | Global path                          |
|------------------|------------------------------|--------------------------------------|
| `standard`       | `.agents/skills/<skill>`     | `~/.config/agents/skills/<skill>`    |
| `codex`          | `.agents/skills/<skill>`     | `$CODEX_HOME/skills/<skill>`         |
| `claude-code`    | `.claude/skills/<skill>`     | `~/.claude/skills/<skill>`           |
| `cursor`         | `.agents/skills/<skill>`     | `~/.cursor/skills/<skill>`           |
| `gemini-cli`     | `.agents/skills/<skill>`     | `~/.gemini/skills/<skill>`           |
| `github-copilot` | `.agents/skills/<skill>`     | `~/.copilot/skills/<skill>`          |
| `opencode`       | `.agents/skills/<skill>`     | `~/.config/opencode/skills/<skill>`  |
| `windsurf`       | `.windsurf/skills/<skill>`   | `~/.codeium/windsurf/skills/<skill>` |
| `eve`            | `agent/skills/<skill>`       | Not supported                        |
| `generic`        | Managed block in `AGENTS.md` | Not supported                        |
