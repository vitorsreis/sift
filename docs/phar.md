# PHAR

Sift releases publish a standalone PHAR.

## Build

```bash
composer build:phar
```

The PHAR includes:

- `src`
- `resources`
- `skills`
- internal PSR-4 bootstrap for `Sift\` classes

It must not depend on `vendor/autoload.php` next to the PHAR at runtime.

## Stub

The stub checks:

- PHP `>= 8.3`
- `ext-json`
- `ext-simplexml`

Runtime commands use the same terminal and JSON renderers as the Composer and `vendor/bin/sift` entrypoints, including `--json`, `--no-json`, `output.colored`, and `--no-color`.

Bootstrap failures exit with code `3` through the PHAR bootstrap error path because they happen before the Sift renderer is available.

## Runtime Paths

Writable paths such as history, temp files, locks, and GitHub clones must live outside the PHAR.

Bundled resources are read through `ResourcePathResolver`.

## Release

Official releases publish:

- `sift.phar`
- SHA-256 checksum
- signature or provenance attestation

Release tags use `vX.Y.Z` and must match `Sift::VERSION`.

Validate the checksum after downloading both assets:

```bash
sha256sum -c sift.phar.sha256
```

GitHub releases also include provenance attestation for the PHAR. Verify it with the GitHub CLI:

```bash
gh attestation verify sift.phar --repo vitorsreis/sift
```

Release CI also exports the Composer package, validates its `composer.json`, installs it in a temporary project, and smoke-tests `vendor/bin/sift`.
