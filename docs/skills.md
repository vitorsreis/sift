# Skills

Sift installs agent instructions from bundled, local, or GitHub sources.

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

Preview discovers skills and returns output without writing targets. Output is terminal text by default; use `--json` for the normalized payload.

## Install

```bash
composer skills add vitorsreis/sift --skill sift --agent=codex
composer skills add owner/repo --skill review --agent=generic --yes
composer skills add owner/repo --all
```

In TTY mode, mutating skill commands show the selected skills and targets before requesting explicit confirmation. In non-TTY or CI, pass `--yes` or `--all`; Sift never relies on an unavailable prompt.

## Targets

- `codex`: copies the full skill directory to the Codex skills home.
- `cursor`: writes `.cursor/rules/<skill>.mdc`.
- `windsurf`: writes `.windsurf/rules/<skill>.md`.
- `claude-code`: updates `CLAUDE.md`.
- `github-copilot` / `vscode`: updates `.github/copilot-instructions.md`.
- `gemini`: updates `GEMINI.md`.
- `generic`: updates `AGENTS.md`.

Targets with unstable path or format contracts are recognized but not write-capable until documented.
Current recognized read-only targets are `antigravity` and `opencode`.

## Managed Metadata

Instruction-file targets use managed blocks:

```text
<!-- sift:skill:<name>:start data="base64url-json" -->
...
<!-- sift:skill:<name>:end -->
```

Copy targets use `.sift-skill.json` inside the installed skill directory.

`list`, `remove`, and `update` read the real targets. There is no `skills-lock.json`.

## Catalog Search

```bash
composer skills find review
```

Search uses `https://skills.sh/api/search` by default and can be overridden with `SKILLS_API_URL` for tests or self-hosted catalogs.
