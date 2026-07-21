<p align="center">
    <img src="resources/logo.svg" alt="Sift" width="128">
</p>

<p align="center">
    <strong>Composer-native skills and agent-friendly tooling for PHP projects.</strong>
</p>

<p align="center">
    <a href="https://packagist.org/packages/vitorsreis/sift"><img alt="PHP Version" src="https://img.shields.io/packagist/php-v/vitorsreis/sift?color=777bb4"></a>
    <a href="https://packagist.org/packages/vitorsreis/sift"><img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/vitorsreis/sift?color=2563eb"></a>
    <a href="https://github.com/vitorsreis/sift/actions/workflows/ci.yml"><img alt="CI" src="https://img.shields.io/github/actions/workflow/status/vitorsreis/sift/ci.yml?branch=master&label=ci"></a>
    <a href="https://github.com/vitorsreis/sift/blob/master/LICENSE.md"><img alt="License" src="https://img.shields.io/github/license/vitorsreis/sift?color=16a34a"></a>
</p>

![Sift preview](resources/preview.svg)

---

Sift helps PHP teams make coding agents more useful with less repeated context.

It adds two Composer-first workflows:

- `composer skills`: install and manage reusable agent skills inside the project.
- `composer sift <tool>`: run PHP tools with compact, normalized, agent-friendly output.

Instead of pasting the same project rules, commands, testing conventions, and huge terminal logs into every session,
Sift keeps instructions and tool results structured. Agents can start from a small summary and inspect stored history
only when they need more detail.

## Why Sift

- **Manage skills through Composer** for the open agent target set, including a standard shared target, Cursor, Claude Code,
  GitHub Copilot, Gemini CLI, OpenCode, Antigravity, Windsurf, and project or global installs.
- **Reduce wasted tokens** by replacing raw tool output with compact summaries and paginated history.
- **Run 20+ PHP tools** through one command layer.
- **Keep automation safer** with read-only Composer reports, dry-run refactors, safe formatter modes, blocked arguments,
  timeouts, and redacted history.
- **Use the same core everywhere** through `composer sift`, `composer skills`, `vendor/bin/sift`, or the PHAR.

## Quick Start

Install Sift globally:

```bash
composer global config allow-plugins.vitorsreis/sift true
composer global require vitorsreis/sift
```

Or install Sift in a single project:

```bash
composer config allow-plugins.vitorsreis/sift true
composer require --dev vitorsreis/sift
```

After installation, initialize Sift in your project:

```bash
composer sift init
```

## Skills

Sift brings a Composer workflow for agent instructions:

```bash
composer skills find
composer skills add owner/repo@skill
```

Supported skill sources:

- bundled skills, such as `sift`;
- local paths;
- GitHub shorthand, such as `owner/repo`;
- GitHub URLs.

Other commands include `init`, `list`, `find`, `use`, `update` / `upgrade`, and `remove`. In terminal mode, `composer skills` opens a bannered help screen and `composer skills find` opens an interactive search with typeahead, arrow-key selection, and Enter-to-select before install.

Without a scope flag, `composer skills add` installs to project skill directories, including the `standard` `.agents/skills` target for Cursor, Gemini CLI, GitHub Copilot, OpenCode, Antigravity, Amp, Replit, and other shared project installs. It accepts multi-value flags such as `--agent standard cursor` and `--skill pr-review commit`. Use `--global` / `-g` for user-level installs, `--subagent reviewer` for Eve subagents, or omit `--agent` in terminal mode to choose agents interactively; existing target folders in the current scope are preselected and Sift prompts for Project or Global scope when applicable.

## Tools

Use `composer sift tools list` to check availability in your project and `composer sift <tool>` to run with compact
output.

For test runners, Sift injects machine-readable reports when needed. If the runner exits before those reports are written, the normalized payload reports the native error message and keeps raw stdout/stderr in `extra`.

#### Supported tools:

- `pest`
- `phpunit`
- `paratest`
- `behat`
- `codeception` / `codecept`
- `phpbench`
- `phpstan`
- `psalm`
- `phpcs`
- `phpcbf`
- `pint`
- `ecs` / `easy-coding-standard`
- `rector`
- `mago`
- `grumphp`
- `infection`
- `deptrac`
- `php-cs-fixer`
- `phpmd`
- `composer-require-checker`
- `composer-unused`
- `composer-normalize` / `normalize`
- `parallel-lint`
- `composer audit|licenses|outdated|show|validate`

## Documentation

- [Getting started](docs/getting-started.md)
- [CLI reference](docs/cli.md)
- [Configuration](docs/configuration.md)
- [Tools](docs/tools.md)
- [Skills](docs/skills.md)
- [Payloads](docs/payloads.md)
- [Architecture](docs/architecture.md)
- [PHAR](docs/phar.md)
- [Contributing](docs/contributing.md)

## License

Released under the [MIT license](LICENSE.md).
