<?php

declare(strict_types=1);

namespace Sift\Output;

final class HelpTerminalRenderer
{
    public function render(): string
    {
        return str_replace("\n", PHP_EOL, <<<'TEXT'
Sift
  Agent tooling and skills layer for PHP projects.

Usage
  composer sift [options] <command> [args]
  composer sift [options] <tool> [args]
  composer skills [options] <command>

Commands
  <tool> [args]                Shortcut for run <tool>.
  run <tool> [args]            Run a tool through Sift.
  tools list                   List supported tools and local availability.

  history list                 List stored runs.
  history view <run_id>        Show a stored run.

  skills list                  List installed skills.
  skills add <source>          Install skills from a source.
  skills find [query]          Search available skills.
  skills init [name]           Scaffold a skill.
  skills remove <skill>        Remove installed skills.
  skills update [skill ...]    Update installed skills.

  init                         Create a sift.json config.
  validate                     Validate sift.json.
  version                      Show the installed Sift version.
  help                         Show this reference.

Options
  --json                       Render normalized JSON for supported commands.
  --no-json                    Force terminal output.
  --compact                    Keep result output short.
  --full                       Show complete result output.
  --pretty, -p                 Pretty-print JSON output.
  --raw                        Stream native tool output.
  --show-process               Show prepared process on STDERR.
  --history / --no-history     Force or skip history for a run.
  --config=<path>, -c <path>   Use a specific config file.

Terminal-only commands
  help, version, tools list     Always render terminal output.

Examples
  composer sift pest
  composer sift --compact phpstan analyse src
  composer sift --json --compact pest
  composer sift --no-json validate
  composer sift --full history view <run_id>
TEXT);
    }
}
