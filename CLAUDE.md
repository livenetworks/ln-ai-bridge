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
- PHPDoc коментари на **македонски** за бизнис описи
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
src/Contracts/                 — интерфејси (AiProviderInterface)
src/DTO/                       — readonly DTOs (Message, AiRequest, AiResponse)
src/Models/                    — Eloquent модели (AiConversation, AiMessage, AiConversationSummary, AiUsageLog)
src/Providers/                 — AbstractProvider, ClaudeProvider, OpenAiProvider
src/Services/                  — ConversationManager, SummarizationService, UsageTracker
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

## Тестирање

```bash
composer test
```
