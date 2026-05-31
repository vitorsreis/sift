# Documentation

Sift v2 is a Composer plugin, CLI binary, PHAR, tool runner, history store, and skill installer for PHP projects.

Start here:

- [COMMANDS.md](COMMANDS.md): CLI and Composer command surface.
- [CONFIGURATION.md](CONFIGURATION.md): `sift.json`, schema, defaults, and precedence.
- [OUTPUTS.md](OUTPUTS.md): output sizes, streams, raw mode, and debug mode.
- [PAYLOADS.md](PAYLOADS.md): normalized JSON payloads and error shape.
- [TOOLS.md](TOOLS.md): supported tools and adapter behavior.
- [ARCHITECTURE.md](ARCHITECTURE.md): module boundaries and execution flow.
- [RELEASE.md](RELEASE.md): Composer package and PHAR release process.

Fast path:

```bash
composer sift init
composer sift validate
composer sift tools list
composer sift --compact pest
composer sift history list
```
