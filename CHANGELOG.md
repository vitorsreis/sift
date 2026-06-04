# Changelog

All notable changes to Sift are documented here.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- Clean-slate Sift v2 implementation with no compatibility layer for v1 runtime contracts.
- Composer plugin bridge with `composer sift` and `composer skills` entrypoints.
- `vendor/bin/sift`, `bin/sift`, and isolated PHAR entrypoint support.
- Supported tool adapters for Pest, PHPUnit, Paratest, PHPStan, Psalm, PHPCS, Pint, Rector, Mago, Infection, Deptrac, PHP-CS-Fixer, PHPMD, Composer dependency tools, Composer Require Checker, Composer Unused, and Parallel Lint.
- Composer report support for read-only `audit`, `licenses`, `outdated`, `show`, and `validate` commands.
- Safety policies for blocked arguments, read-only Composer actions, dry-run refactors, safe formatter modes, raw execution, timeouts, and required machine-readable tool output.
- Normalized payloads for summaries, items, artifacts, extra data, metadata, and errors.
- History storage with secret redaction, retention, truncation, section viewing, removal, and clearing.
- Time-based sortable `run_id` values.
- Versioned GitHub-hosted config schema and `sift.json` init/validation commands.
- PHP proxy entrypoints that preserve PHP `-d` ini options.
- Debug diagnostics on `STDERR` without polluting result output.
- Terminal output renderer as the default CLI format.
- `--json` and `--no-json` flags for explicit output format selection.
- `output.format` config support for `terminal` and `json`.
- Streaming `tools list` availability output with bounded parallel version checks.
- Bundled `sift` skill for agent workflows.
- Skill source support for bundled skills, local paths, GitHub shorthand, and GitHub URLs.
- Skill preview, install, list, find, update, remove, and scaffold commands.
- Managed skill targets for Codex, Cursor, Windsurf, Claude Code, GitHub Copilot, VS Code, Gemini, and generic `AGENTS.md`.
- Recognition of unstable read-only skill targets such as Antigravity and OpenCode.
- Skill source policy checks for unsafe URLs, embedded credentials, path traversal, unsafe symlinks, and submodule-based sources.
- Repeated skill selectors for skill installation.
- Release automation for PHP 8.5, PHAR artifacts, checksums, and provenance.
- Documentation for CLI usage, configuration, tools, skills, payloads, architecture, PHAR, and contributing.
- Baseline quality scripts for Pest, PHPStan, Pint, Rector, and full quality checks through Sift.

### Changed

- Config contract treats `$schema` as editor metadata while init pins the schema URL to the installed Sift version.
- Config loading accepts missing, relative, older, and future schema references.
- `help`, `version`, and `tools list` render terminal output and ignore `--json`.
- Tool-run JSON output emits `run_id` first when a run id is present.
- History records store run fields at the root and metadata under `meta`.
- History ordering uses sortable `run_id` values.
- `show_process` writes process details to `STDERR`.
- Pretty JSON formatting is disabled by default for compact machine output.
- Composer command parsing preserves Sift options placed before tool names, such as `composer sift --json composer validate`.
- Pint parsing follows the real JSON `files[].{path,fixers}` structure.
- Native test runner errors are surfaced instead of being hidden behind parser failures.
- Composer command bridge satisfies Composer's command contract and preserves the project manifest.
- Quality scripts run through Sift entrypoints where possible.

### Removed

- Legacy implementation, tests, docs, and runtime contracts from the active tree.
- JSON output support from `tools list`.
- Legacy nested history `payload.*` records.
- `stored_at` history metadata.

## 1.0.0 - 2026-04-08

### Added

- First public release.
