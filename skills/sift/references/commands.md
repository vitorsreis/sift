# Commands

## Project

```bash
composer sift help
composer sift version
composer sift init
composer sift validate
composer sift tools list
```

## Global Options

```text
--compact
--full
--pretty / --no-pretty
--raw
--show-process / --no-show-process
--debug
--history / --no-history
--config=<path>
```

## Tool Runs

```bash
composer sift --compact pest
composer sift --compact phpunit --filter=Name
composer sift --compact paratest
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact phpcs
composer sift --compact rector process --dry-run src
composer sift --compact pint
composer sift --compact mago lint
composer sift --compact mago analyze src
composer sift --compact mago format --check src
composer sift --compact composer audit
composer sift --compact composer licenses
composer sift --compact composer outdated
composer sift --compact composer show
```

## History

```bash
composer sift history list
composer sift history view <run_id>
composer sift history view <run_id> summary
composer sift history view <run_id> items
composer sift history view <run_id> meta
composer sift history remove <run_id>
composer sift history clear
```

## Skills

```bash
composer skills list
composer skills add sift --agent=codex --yes
composer skills add owner/repo --list
composer skills add owner/repo --skill review --agent=generic --yes
composer skills find review
composer skills init my-skill
composer skills update my-skill --yes
composer skills remove my-skill --yes
```
