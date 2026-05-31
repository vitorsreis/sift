# Tools

`tools list` shows every supported tool, not only installed tools.

Logical status:

- `ON`: installed and enabled.
- `OFF`: missing or disabled.

Supported adapters:

- `phpunit`
- `pest`
- `paratest`
- `phpstan`
- `psalm`
- `phpcs`
- `rector`
- `pint`
- `mago`
- `infection`
- `deptrac`
- `php-cs-fixer`
- `phpmd`
- `composer-unused`
- `composer-require-checker`
- `parallel-lint`
- `composer`

Mutating tools default to safe dry-run or check modes.

Composer supports only read-only subcommands: `audit`, `licenses`, `outdated`, and `show`.
