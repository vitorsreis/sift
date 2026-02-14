# Documentation

This folder documents the current `sift-final` implementation.

## Index

- [ARCHITECTURE.md](ARCHITECTURE.md): runtime flow, policies, and payload lifecycle
- [ADAPTERS.md](ADAPTERS.md): adapter contract and current tool coverage
- [CONFIGURATION.md](CONFIGURATION.md): `sift.json` reference and runtime overrides
- [RELEASE.md](RELEASE.md): PHAR build and release flow

## Command Reference

```bash
sift help
sift version
sift init
sift add <tool>
sift list
sift validate
sift view list
sift view <run_id> [summary|items|meta|artifacts|extra]
sift <tool> [tool-args]
```

## Output Modes

- `compact`: flattened summary for low-token consumption
- `normal`: `run_id`, `summary`, and `items`
- `fuller`: full payload with `meta`, `artifacts`, and `extra`

## Formats

- `json`
- `markdown`
- `--raw` for direct tool passthrough without normalization
