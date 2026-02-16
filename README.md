# Sift

Sift is a PHP CLI wrapper that turns tool output into compact, agent-friendly payloads.

It sits in front of tools such as `phpunit`, `pest`, `paratest`, `phpstan`, `phpcs`, `pint`, `psalm`, `rector`, and `composer-audit`, then normalizes their output into a stable shape that is easier to consume from terminals, agents, and automation.

![Sift preview](resources/preview.svg)

## Installation

Install Sift as a development dependency:

```bash
composer require --dev vitorsreis/sift
```

Or build a local PHAR:

```bash
composer build:phar
php dist/sift.phar help
```

## Quick Start

Initialize a project config:

```bash
vendor/bin/sift init
```

Run a tool through Sift:

```bash
vendor/bin/sift phpstan analyse src
```

Run the test suite and coverage helpers routed through Sift:

```bash
composer test
composer test:coverage
```

Inspect a stored run:

```bash
vendor/bin/sift view list
vendor/bin/sift view <run_id> summary
```

## Documentation

Full documentation lives in [docs/README.md](docs/README.md).
