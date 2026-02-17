<p align="center">
    <img src="resources/logo.svg" alt="Sift" width="128">
</p>

<p align="center">
    <strong>AI-optimized PHP tool wrapper with intelligent output control.</strong>
</p>

<p align="center">
    <a href="https://packagist.org/packages/vitorsreis/sift"><img alt="PHP Version" src="https://img.shields.io/packagist/php-v/vitorsreis/sift?color=777bb4"></a>
    <a href="https://packagist.org/packages/vitorsreis/sift"><img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/vitorsreis/sift?color=2563eb"></a>
    <a href="https://github.com/vitorsreis/sift/actions/workflows/tests.yml"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/vitorsreis/sift/tests.yml?branch=main&label=tests"></a>
    <a href="https://github.com/vitorsreis/sift/blob/main/LICENSE.md"><img alt="License" src="https://img.shields.io/github/license/vitorsreis/sift?color=16a34a"></a>
</p>

![Sift preview](resources/preview.svg)

---

Sift is a complete PHP CLI wrapper that provides controlled execution of PHP tools with structured, token-optimized output designed specifically for AI agents and automated workflows.

## Why Sift

- **Token Reduction**: Dramatically reduce token consumption with intelligent output sizing
- **Complete Control**: Only pre-mapped commands and arguments are allowed
- **Smart Rendering**: Three output modes tuned for different needs (compact/normal/full)
- **Standardized Output**: Unified structure across all tools
- **Process Visibility**: Optional real-time progress display that doesn't affect final output


## Installation

Install Sift as a development dependency:

```bash
composer require --dev vitorsreis/sift
```

Or download the [latest PHAR release](https://github.com/vitorsreis/sift/releases).

## Quick Start

Initialize a project config:

```bash
vendor/bin/sift init
```

Run a tool through Sift:

```bash
vendor/bin/sift phpstan analyse src
```

## Supported Tools

- `phpunit` - PHPUnit test runner
- `pest` - Pest PHP testing framework
- `paratest` - Parallel testing
- `phpstan` - Static analysis
- `phpcs` / `phpcbf` - Code standards checking
- `psalm` - Static analysis tool
- `rector` - Automated refactoring
- `pint` - Laravel Pint code formatter
- `composer` - Composer package manager `audit`, `licenses`, `outdated`, and `show` subcommands

## Documentation

**[Full Documentation](docs/README.md)**

## License

Released under the [MIT license](LICENSE.md).
