# Skills

Sift installs agent instructions from bundled, local, or GitHub sources.

## Terminal Help

```bash
composer skills
```

The root command renders a `SIFT SKILLS` banner and command reference in terminal mode. Terminal feedback uses ANSI styling by default; set `output.colored=false` for plain terminal text by default or pass `--no-color` for one command.

When Sift is installed globally with `composer global require vitorsreis/sift`, use `sift skills <command>` instead of `composer skills <command>`. Composer's global `vendor/bin` directory must be in your `PATH`.

## Sources

- `sift`: bundled Sift skill.
- local path to one skill.
- local path to a repository with several skills.
- `owner/repo`.
- `https://github.com/owner/repo`.

Unsafe sources such as HTTP, SSH, Git protocol URLs, embedded credentials, path traversal, source symlinks, nested target escapes, and submodule-based sources are rejected before clone or copy.

## Preview

```bash
composer skills add owner/repo --list
```

Preview discovers skills and returns output without writing targets. Output is terminal text by default; use `--json` for the normalized payload or `--no-color` for plain terminal text.

## Install

```bash
composer skills add owner/repo@skill
composer skills add vitorsreis/sift --skill sift --agent=codex
composer skills add owner/repo --skill pr-review commit --agent codex cursor --yes
composer skills add owner/repo --skill review --agent=codex --global --yes
composer skills add owner/repo --subagent reviewer --yes
composer skills add owner/repo --skill review --agent=generic --yes
composer skills add owner/repo --all
```

In TTY mode, `skills add` can prompt for skills, target agents, installation scope, and explicit confirmation. If `--agent` is omitted, the agent prompt preselects existing target folders in the current scope, such as `.agents/skills`, `.windsurf/skills`, `AGENTS.md`, or the matching global directory when `--global` is used; when nothing is detected, `codex` is selected by default. After agents are selected, Sift prompts for Project or Global scope when at least one selected target supports global installs and `--global` was not passed.

In non-TTY or CI, pass `--yes` with `--agent=<target>`, or use `--all`; Sift never relies on an unavailable prompt. Without `--global`, non-interactive installs stay in project scope.

Sift accepts multi-value flags: `--agent codex cursor`, `--skill pr-review commit`, and repeated flags all resolve to the same target/skill lists. `skills a` is an alias for `skills add`. `--copy`, `--full-depth`, and `--dangerously-accept-openclaw-risks` are accepted for compatibility with existing skill-manager invocations; Sift installs by copying files into managed targets.

For Eve, use `--subagent <name>` to install into `agent/subagents/<name>/skills/<skill>/`. Use `--subagent root` for the root `agent/skills/<skill>/` target. If `--subagent` is provided without `--agent`, Sift targets Eve automatically.

## Use Without Installing

```bash
composer skills use owner/repo@skill
composer skills use owner/repo --skill skill
```

`skills use` copies the selected skill to a temporary support directory and prints a prompt containing the `SKILL.md` content plus the support path. It does not write project or global skill targets. Sift currently validates but does not launch `--agent`; pipe the generated prompt into the agent when needed.

## Targets

Sift installs native skill-directory targets in project scope by default. Pass `--global` / `-g` to install, list, update, or remove skills in the target's user-level directory.

Common project paths:

- `codex`, `cursor`, `gemini-cli` / `gemini`, `github-copilot` / `vscode`, `opencode`, `antigravity`, `amp`, and most shared targets: `.agents/skills/<skill>/`.
- `claude-code` / `claude`: `.claude/skills/<skill>/`.
- `windsurf`: `.windsurf/skills/<skill>/`.
- `openclaw`: `skills/<skill>/`.
- `eve`: `agent/skills/<skill>/`; Eve subagents use `agent/subagents/<name>/skills/<skill>/`.
- `generic`: updates `AGENTS.md` as a project-only compatibility target.

Common global paths:

- `codex`: `$CODEX_HOME/skills/<skill>/` or `~/.codex/skills/<skill>/`.
- `claude-code`: `$CLAUDE_CONFIG_DIR/skills/<skill>/` or `~/.claude/skills/<skill>/`.
- `cursor`: `~/.cursor/skills/<skill>/`.
- `gemini-cli`: `~/.gemini/skills/<skill>/`.
- `github-copilot`: `~/.copilot/skills/<skill>/`.
- `opencode`: `~/.config/opencode/skills/<skill>/`.
- `windsurf`: `~/.codeium/windsurf/skills/<skill>/`.

Sift supports target names including `aider-desk`, `amp`, `replit`, `universal`, `antigravity`, `antigravity-cli`, `astrbot`, `autohand-code`, `augment`, `bob`, `claude-code`, `openclaw`, `cline`, `dexto`, `kimi-code-cli`, `loaf`, `warp`, `zed`, `codearts-agent`, `codebuddy`, `codemaker`, `codestudio`, `codex`, `command-code`, `continue`, `cortex`, `crush`, `cursor`, `deepagents`, `devin`, `droid`, `eve`, `firebender`, `forgecode`, `gemini-cli`, `github-copilot`, `goose`, `hermes-agent`, `inference-sh`, `jazz`, `junie`, `iflow-cli`, `kilo`, `kiro-cli`, `kode`, `lingma`, `mcpjam`, `mistral-vibe`, `moxby`, `mux`, `opencode`, `openhands`, `ona`, `pi`, `qoder`, `qoder-cn`, `qwen-code`, `reasonix`, `rovodev`, `roo`, `tabnine-cli`, `terramind`, `tinycloud`, `trae`, `trae-cn`, `windsurf`, `zencoder`, `zenflow`, `neovate`, `pochi`, `promptscript`, and `adal`. Project-only targets such as `eve`, `promptscript`, and `generic` are excluded from global `--all`.

## Managed Metadata

The `generic` instruction-file target uses managed blocks:

```text
<!-- sift:skill:<name>:start data="base64url-json" -->
...
<!-- sift:skill:<name>:end -->
```

Skill-directory targets use `.sift-skill.json` inside the installed skill directory.

`list`, `remove`, and `update` read the real targets. There is no `skills-lock.json`.

## Update

```bash
composer skills update
composer skills upgrade review --agent codex --yes
composer skills update --project --yes
composer skills update --global --yes
```

`skills upgrade` is an alias for `skills update`. With named skills and no explicit scope, Sift checks both project and global installs, skipping global-only work for project-only targets. With no names, `--project` updates project skills, `--global` updates global skills, both flags update both, and `--yes` autodetects project skills before falling back to global. In terminal mode without scope flags, Sift asks for `Project`, `Global`, or `Both`.

## Catalog Search

```bash
composer skills find
composer skills find review
composer skills find review --owner vitorsreis
```

Without a query in terminal mode, `skills find` opens an interactive typeahead search. Type to filter, use the arrow keys to move the selected skill, press Enter to select and then install, or Escape to cancel. The prompt shows visual feedback while searching and prevents installation until a result is selected.

Search uses `https://skills.sh/api/search` by default and can be overridden with `SKILLS_API_URL` for tests or self-hosted catalogs. Use `--json` for the normalized payload.
