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
- Composer autoload metadata

It must not depend on `vendor/autoload.php` next to the PHAR at runtime.

## Stub

The stub checks:

- PHP `>= 8.3`
- `ext-json`
- `ext-simplexml`

Bootstrap failures are JSON errors on `STDERR` with exit code `3` because they happen before the Sift renderer is available.

## Runtime Paths

Writable paths such as history, temp files, locks, and GitHub clones must live outside the PHAR.

Bundled resources are read through `ResourcePathResolver`.

## Release

Official releases publish:

- `sift.phar`
- SHA-256 checksum
- signature or provenance attestation

Release tags use `vX.Y.Z` and must match `Sift::VERSION`.
