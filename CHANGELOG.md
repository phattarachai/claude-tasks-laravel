# Changelog

All notable changes to `phattarachai/claude-tasks-laravel` are documented here.

Release notes are drafted automatically from merged pull requests and published on the
[Releases page](https://github.com/phattarachai/claude-tasks-laravel/releases) — that page is the authoritative log.

This file records anything released before that automation landed.

## v0.3.0 — 2026-08-31

**Split the answer, log the call.** A model that narrates around its JSON no longer loses the read, the
prose is handed back on its own, and every run's prompt + parameters are captured.

- **JSON recovered from surrounding prose.** `OutputValidator` carves the JSON object out of any
  narration the model leaked in around it — the last balanced `{…}`, counted string-aware — instead of
  failing a strict `json_decode` on the whole message. Only genuinely object-free output still throws.
- **`TaskResponse->narration`.** The prose the model wrote around the JSON, split out, so a caller can
  show the explanation beside `->output`. `->text` stays the raw full message, verbatim.
- **`#[ResponseFormat(Format::Text)]`.** A task can opt out of JSON and return Markdown / plain prose
  verbatim — the schema gate is skipped and the deliverable is `->narration` / `->text`.
- **`TaskResponse->request` — a `RunManifest`.** The composed prompt plus the resolved parameters:
  requested and actual model, session id, tools, max-turns, timeout, allowed tools, MCP servers,
  output-format, response-format. The `actual*` fields come from the stream's `system`/`init` line.
- **The recorder logs it.** With `phattarachai/task-runs-laravel` ≥ v0.3 installed, `TaskRunRecorder`
  writes the manifest into the run's `request` column — at start (so a failure before the model returns
  still logs the prompt) and enriched with the actuals on success. `claude-tasks.log_prompt` (default on)
  gates storing the prompt text.

Pairs with `phattarachai/task-runs-laravel` ≥ v0.3.0 for the recorder path.

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
