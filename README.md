# Claude Tasks for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/phattarachai/claude-tasks-laravel.svg?style=flat-square)](https://packagist.org/packages/phattarachai/claude-tasks-laravel)
[![Tests](https://img.shields.io/github/actions/workflow/status/phattarachai/claude-tasks-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/phattarachai/claude-tasks-laravel/actions/workflows/run-tests.yml?query=branch%3Amain)
[![Code Style](https://img.shields.io/github/actions/workflow/status/phattarachai/claude-tasks-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/phattarachai/claude-tasks-laravel/actions/workflows/fix-php-code-style-issues.yml?query=branch%3Amain)
[![PHP Version](https://img.shields.io/packagist/dependency-v/phattarachai/claude-tasks-laravel/php?style=flat-square&label=php&logo=php&logoColor=white)](https://packagist.org/packages/phattarachai/claude-tasks-laravel)
![Laravel Version](https://img.shields.io/badge/laravel-12%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white)
[![Total Downloads](https://img.shields.io/packagist/dt/phattarachai/claude-tasks-laravel.svg?style=flat-square)](https://packagist.org/packages/phattarachai/claude-tasks-laravel)

Headless Claude task runner. Declare a **Task class** — a prompt, a JSON output schema, optional context — and run it
through the **Claude Code CLI** (`claude -p`, your subscription's OAuth login, no API key). You get **schema-validated
JSON** back, with cost / turns / duration / session id attached. The API deliberately rhymes with the official
`laravel/ai` SDK, so a Task feels like an Agent.

## The security stance

**The model returns data; the app writes the database.** A Task never lets Claude touch your system:

- **No tools at all by default.** Headless mode denies every tool request that was not allowed up front, and this
  package passes no `--allowedTools` unless the Task declares them with `#[AllowedTools(...)]`. The default run can
  only read its prompt and answer.
- **MCP is opt-in per Task**, and the `--mcp-config` file is generated at runtime from *this* machine's `PHP_BINARY`
  and `base_path()` — never a path copied from another box. The bundled `McpServer::laravelBoost()` preset spawns
  `php artisan boost:mcp`; pair it with the read-only `mcp__laravel-boost__database-query` tool, never tinker.
- **Attachments allow exactly `Read`.** Declaring `attachments()` lists the files in the prompt and allowlists the
  Read tool — nothing else.
- **The model follows the CLI's default** unless pinned — `CLAUDE_TASKS_MODEL` / `claude-tasks.model` or a Task's `#[Model]` adds `--model`; `--max-turns` is always capped.
- **Failures throw typed exceptions** — error text is never returned as a result.
- JSON output is recovered even when the model narrates before it — the last balanced `{…}` object is carved out of
  any leading prose. Output with no JSON object at all, or JSON that misses the declared schema, throws
  `InvalidTaskOutput` with the validation errors and the raw output attached. Tasks can also opt out of JSON entirely
  with `#[ResponseFormat(Format::Text)]` (below).

## Install

```bash
composer require phattarachai/claude-tasks-laravel
php artisan vendor:publish --tag=claude-tasks-config   # optional
php artisan claude-tasks:doctor                        # binary found? version? OAuth healthy? add --probe for a real call
```

The machine running the app (or its queue workers) needs a logged-in Claude Code (`claude` then `/login`).

## Declare a Task

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Phattarachai\ClaudeTasksLaravel\Concerns\Runnable;
use Phattarachai\ClaudeTasksLaravel\Contracts\HasContext;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;

class CategorizeStatement implements HasContext, Task
{
    use Runnable;

    public function __construct(public Statement $statement) {}

    public function instructions(): string
    {
        return 'Categorize each statement line into the given categories.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->array()->items($schema->object([
                'description' => $schema->string()->required(),
                'category' => $schema->string()->enum(['food', 'transport', 'income', 'other'])->required(),
                'amount' => $schema->number()->required(),
            ]))->required(),
        ];
    }

    public function context(): array
    {
        return ['Statement lines' => $this->statement->rawLines()];
    }
}
```

`schema(JsonSchema $schema): array` is the same signature as `laravel/ai`'s structured output — the fluent
`illuminate/json-schema` types. The schema is embedded in the prompt as the output contract *and* enforced on the way
back with Laravel's validator.

### Options via attributes

```php
#[Model('claude-opus-5')]
#[Timeout(120)]
#[MaxTurns(5)]
#[AllowedTools('Read', 'mcp__laravel-boost__database-query')]
class AuditLedger implements Task { /* … */ }
```

Anything not declared falls back to `config/claude-tasks.php` (`model`, `timeout`, `max_turns`).

### Text responses

Not every task returns data. Pin `#[ResponseFormat(Format::Text)]` for a task whose deliverable is Markdown or plain
prose — the schema gate is skipped, the prompt asks for the answer alone, and the reply comes back verbatim on
`$response->text` (only surrounding whitespace trimmed; `$response->output` is `[]`). The task declares no schema.

```php
use Phattarachai\ClaudeTasksLaravel\Attributes\ResponseFormat;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;

#[ResponseFormat(Format::Text)]
class SummarizeMonth implements HasContext, Task
{
    use Runnable;

    public function instructions(): string
    {
        return 'Write a short Markdown summary of the month.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

$summary = SummarizeMonth::make($month)->run()->text;   // Markdown prose
```

The default is `Format::Json` — omit the attribute and the declared `schema()` is enforced as before.

### Optional capabilities

| Interface | Method | Effect |
|---|---|---|
| `HasContext` | `context(): array` | Labeled sections appended to the prompt, piped through `claude-tasks.context.pipes` |
| `HasAttachments` | `attachments(): array` | File list in the prompt + automatic `Read` allow |
| `UsesMcpServers` | `mcpServers(): array` | Runtime-generated `--mcp-config`, e.g. `['laravel-boost' => McpServer::laravelBoost()]` |

## Run it

```php
$response = CategorizeStatement::make($statement)->run();   // or ClaudeTasks::run($task)

$response->output;                    // schema-validated array — write your DB with it
$response['lines'];                   // ArrayAccess into the output
$response->json('lines.0.category');  // dot access
$response->usage->costUsd;            // plus numTurns / durationMs / sessionId
$response->narration;                 // prose the model wrote around the JSON (object removed), '' if none
$response->text;                      // the raw final message, unchanged
$response->request;                   // RunManifest — what was actually sent (below)
```

`output` and `narration` are the two halves of the final message: when the model narrates around its answer, the last
balanced `{…}` object is validated into `output` and the surrounding prose is handed back on `narration` (empty when
the message was pure JSON). `text` stays the untouched raw message.

`request` is a **`RunManifest`** — the composed prompt and the resolved call parameters: the requested model and the
**actual** model / session id / tools captured from the stream's `system`/`init` line, plus max-turns, timeout, allowed
tools, MCP servers, and the output-format / response-format. `->toArray()` gives you the loggable shape.

Or queue it with the `laravel/ai`-style callbacks (tries / backoff / queue from config):

```php
CategorizeStatement::make($statement)->queue()
    ->then(fn (TaskResponse $response) => $statement->applyCategories($response->output))
    ->catch(fn (Throwable $e) => report($e));
```

Failures throw (or fail the job) with typed exceptions: `ClaudeProcessFailed`, `ClaudeAuthExpired` (login needed on
this machine), `InvalidTaskOutput` (`->errors`, `->rawOutput`).

Events fire around every run: `TaskStarting`, `TaskCompleted`, `TaskFailed`.

## Watch it think (streaming)

Attach a progress listener and the run switches to the CLI's event stream
(`--output-format stream-json --verbose`) — same process, read line by line, events delivered **as they arrive**
instead of after exit. Nothing else changes: the closing result is still validated against `schema()`, failures still
throw the same typed exceptions, and the `#[Timeout]` still bounds the whole run.

```php
use Phattarachai\ClaudeTasksLaravel\Streaming\AssistantText;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Streaming\ResultReceived;
use Phattarachai\ClaudeTasksLaravel\Streaming\RunStarted;
use Phattarachai\ClaudeTasksLaravel\Streaming\ToolUseStarted;

$response = ReadInvoice::make($file)
    ->onProgress(fn (ProgressEvent $event) => match (true) {
        $event instanceof AssistantText => $run->reportProgress($event->text),
        $event instanceof ToolUseStarted => $run->reportProgress("{$event->name} — {$event->summary}"),
        default => null,
    })
    ->run();

$response->output;   // unchanged: schema-validated array
```

| Event | Carries |
|---|---|
| `RunStarted` | `sessionId`, `model`, `tools` — the CLI's `system`/`init` line, once |
| `AssistantText` | `text` — one trimmed text block per turn |
| `ToolUseStarted` | `name`, `summary` (the telling argument, truncated to 120 chars), `input` |
| `ResultReceived` | `text`, `usage`, `isError` — always last |

Every event also carries `->raw`, the undecoded line, for anything the typed shape leaves out. Tool *results* are
never surfaced: the feed says "reading invoice.jpg", never the file body. Unknown line types are skipped rather than
thrown on, so a newer CLI cannot break a run.

Sync JSON stays the default — a Task with no listener behaves exactly as it did in v0.1. Ask the model to narrate its
steps in `instructions()` if you want the text blocks to read like an activity feed.

`onProgress()` returns a `PendingRun`, not the Task, so the Task itself stays queue-serializable (a Closure is not).
There is deliberately no `queue()` on it — a listener only makes sense in the process that is watching, so queue a job
of your own and call `->onProgress(…)->run()` inside it.

## task-runs integration

When [`phattarachai/task-runs-laravel`](https://github.com/phattarachai/task-runs-laravel) is installed, every Claude
run records one `task_runs` row — type from the Task class, cost / turns / model / session id in `options` — so runs
show up in the same in-app task screen as your other background work. Toggle with `claude-tasks.task_runs.enabled`.

With task-runs ≥ v0.3, `TaskRunRecorder` also persists the run's `RunManifest` into the row's `request` column — the
composed prompt and every resolved parameter, so a run is reproducible and a pre-return failure still logs what it
tried. `claude-tasks.log_prompt` (default `true`) gates storing the prompt text; turn it off to keep the parameters
without the prompt body.

## Testing your app

```php
ClaudeTasks::fake();                                       // schema-derived fake output
ClaudeTasks::fake([CategorizeStatement::class => [...]]);  // or canned / Closure output

CategorizeStatement::make($statement)->run();

ClaudeTasks::assertRan(CategorizeStatement::class, fn ($task) => $task->statement->is($statement));
ClaudeTasks::assertQueued(CategorizeStatement::class);
ClaudeTasks::assertNothingRan();
```

The fake never touches the CLI, and its generated output passes the Task's own schema.

Fake a progress sequence to test your listener — the replay closes with a `ResultReceived` carrying the fake output,
the way a real stream ends, unless your sequence already provides one:

```php
ClaudeTasks::fake()->withProgress(CategorizeStatement::class, [
    new AssistantText('เจอ Tax ID 0105558…'),
    new ToolUseStarted('mcp__laravel-boost__database-query', 'select * from partners'),
]);
```

## Health

`php artisan claude-tasks:doctor` reports the resolved binary, its version, the OAuth credentials file, token expiry,
and whether a refresh token is present — exit code 1 when a scheduled run would die on auth.

Those are metadata checks, and metadata can lie: on macOS the CLI prefers a `Claude Code-credentials` Keychain item
over the credentials file, and GUI/launchd processes (Horizon started from the desktop) can read a different copy than
ssh sessions do — a stale Keychain token fails workers while the file still looks healthy. The doctor warns when that
Keychain item exists (metadata only, via `security find-generic-password`; it never reads the secret), and
`--probe` runs a real one-turn headless call through the same `ClaudeCommand` path queued tasks use, reporting
pass/fail with the failure classified as authentication or process:

```bash
php artisan claude-tasks:doctor --probe
```

## Not in v1

No chat mode, no interactive sessions, no tool-result plumbing back into your app. One prompt in, one validated JSON
object out — with an optional read-only view of the work in between.

## License

MIT — see [LICENSE.md](LICENSE.md).
