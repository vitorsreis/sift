# Release

Sift currently ships PHAR builds through `composer build:phar`.

The repository also ships a `box.json` aligned with the same thin-distribution layout for projects that prefer Box-based release automation.

## Local Build

```bash
composer install --prefer-dist --no-interaction
composer build:phar
php build/phar/sift.phar help
```

The build writes:

- `build/phar/sift.phar`
- `build/phar/sift.phar.sha256`

## Checksum Verification

### Linux

```bash
sha256sum -c build/phar/sift.phar.sha256
```

### macOS

```bash
shasum -a 256 -c build/phar/sift.phar.sha256
```

### PowerShell

```powershell
$expected = (Get-Content .\build\phar\sift.phar.sha256).Split(' ')[0]
$actual = (Get-FileHash .\build\phar\sift.phar -Algorithm SHA256).Hash.ToLower()
if ($expected -ne $actual) { throw 'Checksum mismatch for sift.phar' }
```

## Packaging Notes

- the PHAR includes `src`, `resources`, and release metadata only
- runtime dependencies stay outside the archive in `vendor/`
- the stub resolves `vendor/autoload.php` next to `sift.phar` or in parent directories when the archive lives under `build/phar/`
- the PHAR bootstraps `Sift\Console\Application::run()`
- `bin/phar` and `box.json` share the same `resources/box.stub.php` bootstrap entrypoint
