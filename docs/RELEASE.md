# Release

Releases use SemVer tags:

```text
vX.Y.Z
```

The tag must match `Sift::VERSION`.

Release artifacts:

- Composer package.
- `sift.phar`.
- SHA-256 checksum.
- Signature or provenance attestation for official releases.

Before release:

```bash
composer validate --strict
composer quality
composer build:phar
```

The release schema URL is:

```text
https://raw.githubusercontent.com/vitorsreis/sift/vX.Y.Z/resources/schema.json
```
