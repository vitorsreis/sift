# Architecture

Main flow:

```text
Entrypoint
  -> Console\Application
  -> Console\CliParser
  -> Console\CommandRouter
  -> Console\Commands\CommandHandler
  -> Workspace\WorkspaceResolver
  -> Config\SiftConfig
  -> Tools\ToolRunner
  -> Safety\PolicyPipeline
  -> Execution\ProcessRunner
  -> Tools\ToolAdapter
  -> Output\PayloadSizer
  -> History\RunStore
  -> Output\Renderer
```

Rules:

- Console parses and routes. It does not know adapter internals.
- Config is typed before use.
- Tool resolution happens once per execution.
- Policies run before process execution.
- Adapters prepare commands and parse native output.
- History stores one JSON file per run.
- Skills use managed blocks or managed metadata files.
- PHAR reads bundled resources through `ResourcePathResolver`.
