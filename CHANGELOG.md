# Changelog

All notable changes to Sift are documented here.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.2.0 - 2026-06-25

### Added

- `composer sift help` now renders a grayscale `SIFT` banner, matching the skills helper visual style.
- `output.colored` configures terminal ANSI color feedback globally.
- `--no-color` disables ANSI styling for a single terminal command.
- Skills targets now include OpenCode, Antigravity, Gemini CLI, OpenClaw, and other `.agents/skills`-based agents.
- `skills add` now prompts interactively for Project or Global scope when selected agents support user-level installs.
- `skills use` generates a one-off prompt from a selected skill without installing it.
- `skills add --subagent <name>` installs to Eve subagent directories.

### Changed

- Terminal renderers now use one color contract: commands in cyan, options and placeholders in yellow, headings in bold, secondary text in gray, success in green, and errors in red.
- Skills help and Sift help now build styled output from structured rows instead of ad-hoc token replacements.
- Composer skills entrypoints now accept output flags such as `--json`, `--compact`, and `--no-color` consistently.
- Native skill targets now install into project skill directories by default; `--global` / `-g` selects the user-level target directory for add, list, remove, and update.
- The interactive agent picker now preselects existing target folders and falls back to `codex` when no target folder is detected.
- Skills commands now accept Skills CLI-style aliases and multi-value flags, including `skills a`, `skills upgrade`, `--agent codex cursor`, and `--skill pr-review commit`.
- `skills update` now follows the Skills CLI scope rules: named updates check project and global unless `--project` or `--global` is provided, and `--yes` autodetects project skills before falling back to global.

### Fixed

- Composer skills tests now isolate `CODEX_HOME` so local installed skills do not leak into fixture expectations.
- Terminal output tests now compare plain content after stripping ANSI sequences where color is not the behavior under test.
- `--global` on skills commands now affects the actual inventory and install/remove/update paths instead of only appearing in metadata.

## 2.1.0 - 2026-06-24

### Added

- `skills find` now supports an interactive typeahead search when no query is provided in a TTY.
- Catalog search accepts `--owner` and installable `owner/repo@skill` shorthands.
- `skills add` can prompt interactively for skills, target agents, and confirmation.

### Changed

- `skills find <query>` now renders compact terminal output like the public Skills CLI by default while preserving `--json`.
- Skill catalog results preserve installs, slugs, and source URLs when returned by `skills.sh`.

## 2.0.0 - 2026-06-04

Sift 2.0 is a new major version rebuilt around a simpler CLI, normalized result payloads, Composer-native entrypoints, managed skills, and safer tool execution. It does not keep compatibility with Sift 1.x runtime contracts.

### Breaking Changes

- Removed the Sift 1.x implementation, tests, documentation, and runtime compatibility layer from the active codebase.
- Sift now requires PHP 8.3 or newer.
- History records now store run fields at the root and metadata under `meta`; the old nested `payload.*` shape was removed.
- `stored_at` history metadata was removed.
- `tools list` is terminal-only and no longer supports JSON output.
- `help`, `version`, and `tools list` ignore `--json` and always render terminal output.
- `$schema` is treated as editor metadata, not as a runtime compatibility gate. `sift init` still pins the schema URL to the installed Sift version.
- Consumers that parsed Sift 1.x history, tool lists, or config validation behavior must update their integrations for the 2.x contracts.

### Migration From 1.x

- Upgrade with Composer using the 2.x package line.
- Regenerate or review `sift.json` with the new config schema before relying on previous settings.
- Update any automation that reads history records from `payload.*`; Sift 2.x writes the run fields at the root.
- Remove any automation that expects JSON output from `tools list`; use terminal output for discovery or run specific tools directly for machine-readable results.
- Review command wrappers that depend on Sift 1.x output ordering or history metadata.
- Reinstall or update managed skills when moving existing agent workflows to the new skill manager.

### Added

- Composer plugin bridge with `composer sift` and `composer skills` commands.
- CLI entrypoints for `vendor/bin/sift`, `bin/sift`, and isolated PHAR usage.
- PHP proxy entrypoints that preserve PHP `-d` ini options.
- Supported adapters for Pest, PHPUnit, Paratest, PHPStan, Psalm, PHPCS, Pint, Rector, Mago, Infection, Deptrac, PHP-CS-Fixer, PHPMD, Composer dependency tools, Composer Require Checker, Composer Unused, and Parallel Lint.
- Read-only Composer report support for `audit`, `licenses`, `outdated`, `show`, and `validate`.
- Normalized result payloads for summaries, items, artifacts, extra data, metadata, errors, and `run_id`.
- Time-sortable `run_id` values.
- History storage with redaction, retention, truncation, section viewing, removal, and clearing.
- `sift init` and config validation against the bundled JSON Schema.
- Terminal output as the default CLI renderer.
- `--json`, `--no-json`, and `output.format` support for tool-run output.
- Bounded parallel availability checks for `tools list`.
- Bundled `sift` skill for agent workflows.
- Skill management commands for listing sources, installing, finding, updating, removing, and scaffolding skills.
- Skill sources from bundled catalogs, local paths, GitHub shorthand, and GitHub URLs.
- Managed skill targets for Codex, Cursor, Windsurf, Claude Code, GitHub Copilot, VS Code, Gemini, and generic `AGENTS.md`.
- Recognition of unstable read-only skill targets such as Antigravity and OpenCode.
- Release automation for PHAR artifacts, checksums, provenance, exported Composer packages, and dev branch installation validation.
- Documentation for CLI usage, configuration, tools, skills, payloads, architecture, PHAR usage, and contributing.
- Quality scripts for Pest, PHPStan, Pint, Rector, and full checks through Sift.

### Changed

- Config loading now accepts missing, relative, older, and future schema references.
- Tool-run JSON output emits `run_id` first when present.
- `show_process` writes diagnostics to `STDERR` so result output remains parseable.
- Debug output is redacted and written to `STDERR`.
- Pretty JSON is disabled by default for compact machine output.
- Composer command parsing preserves Sift options placed before tool names, such as `composer sift --json composer validate`.
- Composer command bridge now satisfies Composer's command contract and preserves the project manifest.
- Installed `vendor/bin/sift` uses Composer's injected autoload path, with source-tree fallback for local execution.
- Quality scripts run through Sift entrypoints where possible.
- Rector results distinguish real diffs from inconsistent changed-file list noise.
- Process timeouts terminate descendant processes best-effort.
- Atomic writes preserve existing permissions and restrict new files.
- Skill mutations request explicit confirmation in TTY and require `--yes` or `--all` otherwise.
- Composer archives exclude generated builds, dependency folders, caches, and local run history.
- Documentation now describes Composer `validate` support, PHAR bootstrap, and the supported 2.x security line.

### Fixed

- Pint JSON parsing now follows the real `files[].{path,fixers}` structure.
- Native test runner errors are surfaced instead of being hidden behind parser failures.
- Composer `validate` warnings are normalized as warning items instead of fatal result validation errors.
- Skill catalog results tolerate missing descriptions by generating a safe fallback.
- Composer Require Checker reports unknown symbols without guessed packages instead of treating them as parser failures.
- Windows batch argument escaping is covered by a command-injection regression test.
- Config validation shares timeout defaults with runtime.
- History redaction covers embedded short secrets in flags, URLs, queries, paths, and standalone values that self-identify as tokens or secrets.

### Security

- Added safety policies for blocked arguments, read-only Composer actions, dry-run refactors, safe formatter modes, raw execution, timeouts, and required machine-readable tool output.
- Added skill source policy checks for unsafe URLs, embedded credentials, path traversal, source symlinks, nested target escapes, and submodule-based sources.
- Codex skill installs validate source trees and replace targets from restricted staging directories.

### Removed

- Sift 1.x runtime contracts and compatibility paths.
- JSON output support from `tools list`.
- Legacy nested history `payload.*` records.
- `stored_at` history metadata.

## 1.0.0 - 2026-04-08

### Added

- First public release.
