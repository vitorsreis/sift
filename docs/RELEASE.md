# Release

Sift currently ships PHAR builds through `composer build:phar`.

The repository also ships a `box.json` aligned with the same thin-distribution layout for projects that prefer Box-based release automation.

## Local Build

```bash
composer install --prefer-dist --no-interaction
composer build:phar
php dist/sift.phar help
```

The build writes:

- `dist/sift.phar`
- `dist/sift.phar.sha256`

## Checksum Verification

### Linux

```bash
sha256sum -c dist/sift.phar.sha256
```

### macOS

```bash
shasum -a 256 -c dist/sift.phar.sha256
```

### PowerShell

```powershell
$expected = (Get-Content .\dist\sift.phar.sha256).Split(' ')[0]
$actual = (Get-FileHash .\dist\sift.phar -Algorithm SHA256).Hash.ToLower()
if ($expected -ne $actual) { throw 'Checksum mismatch for sift.phar' }
```

## Packaging Notes

- the PHAR includes `src`, `resources`, and release metadata only
- runtime dependencies stay outside the archive in `vendor/`
- the stub resolves `vendor/autoload.php` next to `sift.phar` or one directory above it
- the PHAR bootstraps `Sift\Console\Application::run()`
- `bin/phar` and `box.json` share the same `resources/box.stub.php` bootstrap entrypoint
