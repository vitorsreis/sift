# Commands

Entrypoints:

```bash
composer sift <command>
composer skills <command>
php vendor/bin/sift <command>
php sift.phar <command>
```

Common runs:

```bash
composer sift --compact pest
composer sift --compact phpunit
composer sift --compact paratest
composer sift --compact phpstan analyse src
composer sift --compact psalm
composer sift --compact phpcs
composer sift --compact rector process --dry-run src
composer sift --compact pint
composer sift --compact mago lint
composer sift --compact composer audit
```

History:

```bash
composer sift history list
composer sift history view <run_id>
composer sift history view <run_id> summary
composer sift history view <run_id> items
composer sift history remove <run_id>
```

Skills:

```bash
composer skills add sift --agent=codex --yes
composer skills add owner/repo --list
composer skills list
composer skills find review
composer skills init my-skill
composer skills update my-skill --yes
composer skills remove my-skill --yes
```
