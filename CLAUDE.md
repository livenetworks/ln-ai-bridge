# LN AI Bridge — Project Instructions

## Проект

Laravel Composer package `livenetworks/ln-ai-bridge` — AI provider abstraction layer.
PHP 8.3+, Laravel 11+/12+, Guzzle 7.8+ за HTTP. Без external AI SDK-ови.

## Namespace

`LiveNetworks\LnAiBridge`

## Архитектура

Bridge-от е **"dumb pipe"** — НЕ знае за бизнис логика.
Контекстот доаѓа од апликацијата: `Controller → Models → PromptBuilder → Bridge → Provider → API`.

## Конвенции

- `declare(strict_types=1)` во секој PHP фајл
- `readonly` за сите DTO класи
- PHPDoc коментари на **англиски**
- Контекст injection преку XML тагови: `<key>value</key>`
- Без external AI SDK-ови — директен Guzzle HTTP
- UUID за PK на conversations, messages, summaries (HasUuids trait)
- Auto-increment за usage_log (append-only, не треба UUID)
- `const UPDATED_AT = null` за immutable модели (messages, summaries, usage_log)
- tenant_id е САМО за billing/tracking, nullable — bridge-от НЕ филтрира по него
- SoftDeletes само на conversations

## Структура

```
config/ai-bridge.php           — publishable config
database/migrations/           — publishable миграции (tag: ai-bridge-migrations)
src/Contracts/                 — интерфејси (AiProviderInterface, ToolExecutorInterface)
src/DTO/                       — readonly DTOs (Message, AiRequest, AiResponse, Tool, ToolCall, ToolResult)
src/Models/                    — Eloquent модели (AiConversation, AiMessage, AiConversationSummary, AiUsageLog)
src/Providers/                 — AbstractProvider, ClaudeProvider, OpenAiProvider
src/Services/                  — ConversationManager, SummarizationService, UsageTracker, ToolRunner, RetryHandler
src/PromptBuilder.php          — fluent builder за AiRequest
src/AiBridgeManager.php        — singleton оркестратор (+ auto usage tracking)
src/Facades/AiBridge.php       — Laravel Facade
src/AiBridgeServiceProvider.php — Service Provider
```

## Додавање нов провајдер

1. Креирај класа во `src/Providers/` што extends `AbstractProvider`
2. Имплементирај: `name()`, `model()`, `endpoint()`, `buildHeaders()`, `buildPayload()`, `parseResponse()`
3. Регистрирај во `AiBridgeManager::$drivers` или преку `AiBridge::register()`
4. Додај конфигурација во `config/ai-bridge.php` providers array

## Tool Use (Function Calling)

The bridge supports tool use — the AI can request data from the backend via tools.
Tool calls are executed automatically and transparently for the caller.

### Flow

1. The application defines tools in the request (via PromptBuilder or AiRequest)
2. The AI returns tool_calls instead of text
3. The bridge automatically executes them via registered ToolExecutorInterface implementations
4. Results are sent back to the AI
5. The AI generates a final text response
6. The caller receives only the final response

### Key classes

- `Tool` (DTO) — tool definition (name, description, parameters JSON Schema)
- `ToolCall` (DTO) — tool call requested by the AI (id, name, arguments)
- `ToolResult` (DTO) — result from an executed tool (toolCallId, content, isError)
- `ToolExecutorInterface` (Contract) — the consuming app implements this for each tool
- `ToolRunner` (Service) — orchestrator that executes tool calls

### Configuration

- `ai-bridge.tools.enabled` — enable/disable tool use (default: true)
- `ai-bridge.tools.max_iterations` — max recursive tool call rounds (default: 5)

## Retry (Automatic Retry with Backoff)

AbstractProvider::send() automatically retries on retryable API failures.
RetryHandler is generic — knows nothing about AI specifics.

### Flow

1. HTTP request fails with retryable status code (429, 500, 502, 503, 529)
2. RetryHandler waits with exponential backoff (base_delay * multiplier^attempt)
3. For 429 with Retry-After header, uses that delay instead
4. Retries up to max_retries times
5. If all retries fail, returns AiResponse::fail()
6. Non-retryable errors (400, 401, 403, 404) fail immediately

### Key classes

- `RetryHandler` (Service) — generic retry with exponential backoff

### Configuration

- `ai-bridge.retry.enabled` — enable/disable retry (default: true)
- `ai-bridge.retry.max_retries` — max retry attempts (default: 3)
- `ai-bridge.retry.base_delay_ms` — base delay in ms (default: 1000)
- `ai-bridge.retry.multiplier` — backoff multiplier (default: 2)
- `ai-bridge.retry.retryable_codes` — status codes to retry (default: [429, 500, 502, 503, 529])

## Тестирање

```bash
composer test
```
