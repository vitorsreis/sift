# Documentation

This folder is the full reference for Sift.

If the repository root README is the project presentation, this folder is where the behavior, commands, config, output model, adapter contract, and release flow are explained in detail.

Coverage note:

- `composer test:coverage` is available, but it requires `xdebug` or `pcov` in the active PHP runtime.
- the GitHub Actions CI includes a dedicated `php-coverage` job with `xdebug`
- that coverage job uploads `build/coverage/clover.xml` as an artifact

## Start Here

- [COMMANDS.md](COMMANDS.md): what every command does, when to use it, reserved commands, interactive `add`, `view`, and runtime flags
- [OUTPUTS.md](OUTPUTS.md): `json` vs `markdown`, `compact` / `normal` / `fuller`, `--raw`, view scopes, and how results are rendered
- [CONFIGURATION.md](CONFIGURATION.md): full `sift.json` reference, defaults, schema, overrides, and config-writing commands
- [ADAPTERS.md](ADAPTERS.md): current supported tools, adapter responsibilities, and how new adapters are added
- [PAYLOADS.md](PAYLOADS.md): normalized result contract, common guarantees, and the per-adapter field matrix
- [ARCHITECTURE.md](ARCHITECTURE.md): runtime flow, policies, history persistence, and payload lifecycle
- [RELEASE.md](RELEASE.md): PHAR build, Box packaging, checksums, and release flow

## Documentation Map

### Commands

Use [COMMANDS.md](COMMANDS.md) if you need to understand:

- the difference between reserved commands and wrapped tool execution
- when `sift add` is explicit or interactive
- how `list`, `validate`, and `view` behave
- what `--config`, `--raw`, `--show-process`, `--pretty`, `--size`, and `--no-history` actually change

### Output and Payloads

Use [OUTPUTS.md](OUTPUTS.md) and [PAYLOADS.md](PAYLOADS.md) if you need to understand:

- which renderer is used for `json` or `markdown`
- what changes between `compact`, `normal`, and `fuller`
- how `view` scopes surface stored results
- when native output is passed through unchanged with `--raw`

### Configuration

Use [CONFIGURATION.md](CONFIGURATION.md) if you need to understand:

- the exact `sift.json` shape
- output defaults
- history defaults and storage location
- per-tool config such as `enabled`, `toolBinary`, `defaultArgs`, and `blockedArgs`
- how CLI flags override config values at runtime

### Tool Support

Use [ADAPTERS.md](ADAPTERS.md) if you need to understand:

- which tools are supported today
- how each adapter normalizes native output
- where write restrictions exist, such as `rector process --dry-run`
- how to add a new adapter safely

### Internals and Release

Use [ARCHITECTURE.md](ARCHITECTURE.md) and [RELEASE.md](RELEASE.md) if you need to understand:

- how Sift moves from CLI args to normalized payloads
- how policies are applied before execution
- how history is persisted and viewed later
- how PHAR builds and Box packaging are kept aligned

## Fast Reference

```bash
sift help
sift version
sift init
sift add [tool]
sift list
sift validate
sift view list
sift view <run_id> [summary|items|meta|artifacts|extra]
sift <tool> [tool-args]
```

## Reading Order

If you are new to the project, the shortest useful path is:

1. [COMMANDS.md](COMMANDS.md)
2. [OUTPUTS.md](OUTPUTS.md)
3. [CONFIGURATION.md](CONFIGURATION.md)
4. [ADAPTERS.md](ADAPTERS.md)

If you are changing internals or release automation, continue with:

1. [PAYLOADS.md](PAYLOADS.md)
2. [ARCHITECTURE.md](ARCHITECTURE.md)
3. [RELEASE.md](RELEASE.md)
