# Changelog

All notable changes to `phattarachai/claude-tasks-laravel` are documented here.

Release notes are drafted automatically from merged pull requests and published on the
[Releases page](https://github.com/phattarachai/claude-tasks-laravel/releases) — that page is the authoritative log.

This file records anything released before that automation landed.

## v0.2.0 — 2026-08-29

**Opt-in streaming.** `SomeTask::make()->onProgress(fn (ProgressEvent $e) => …)->run()` runs the CLI with
`--output-format stream-json --verbose` and delivers typed, immutable events as each line arrives: `RunStarted`,
`AssistantText`, `ToolUseStarted` (name + a truncated argument summary — never tool results), `ResultReceived`.

The synchronous JSON envelope stays the default; a Task with no listener is byte-for-byte what v0.1 ran. The streamed
result goes through the same `schema()` gate, the same `ClaudeAuthExpired` / `ClaudeProcessFailed` classification, and
the same per-run timeout.

- `ClaudeTasksManager::run()` / `ClaudeTasks::run()` take an optional progress Closure.
- `onProgress()` returns a `PendingRun` rather than storing the Closure on the Task, keeping Tasks queue-serializable.
- `ClaudeTasks::fake()` accepts a per-Task progress sequence (`withProgress()`, or a second constructor argument).
