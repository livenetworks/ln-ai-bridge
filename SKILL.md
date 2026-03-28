# LN AI Bridge — Skill

## Опис

`livenetworks/ln-ai-bridge` е AI provider abstraction layer за Laravel.
Обезбедува унифициран интерфејс за комуникација со Claude, OpenAI, и други AI провајдери.

## Користење во апликацијата

### Испраќање AI барање

```php
use LiveNetworks\LnAiBridge\Facades\AiBridge;

$response = AiBridge::prompt()
    ->system('Системска порака.')
    ->context('key', 'value')       // се вметнува како <key>value</key> во prompt
    ->prompt('Корисничко барање.')
    ->temperature(0.5)
    ->maxTokens(1024)
    ->build();

$result = AiBridge::send($response);           // стандарден провајдер
$result = AiBridge::send($response, 'openai'); // експлицитен провајдер
```

### Multi-turn конверзација

```php
use LiveNetworks\LnAiBridge\DTO\Message;

$request = AiBridge::prompt()
    ->history([
        Message::user('Претходно прашање'),
        Message::assistant('Претходен одговор'),
    ])
    ->prompt('Следно прашање')
    ->build();
```

### Работа со одговор (AiResponse)

```php
$result->content;       // текст
$result->success;       // bool
$result->error;         // null | string
$result->totalTokens(); // вкупно токени
$result->provider;      // "claude" | "openai"
$result->model;         // модел
$result->raw;           // суров API одговор
```

## Конфигурација

Фајл: `config/ai-bridge.php`

Env варијабли:
- `AI_BRIDGE_PROVIDER` — стандарден провајдер (claude/openai)
- `AI_BRIDGE_CLAUDE_API_KEY` — Anthropic API клуч
- `AI_BRIDGE_OPENAI_API_KEY` — OpenAI API клуч
- `AI_BRIDGE_LOGGING` — логирање (true/false)

## Конверзации (Conversation History)

```php
use LiveNetworks\LnAiBridge\Services\ConversationManager;

$manager = app(ConversationManager::class);

// Започни нова конверзација
$conversation = $manager->startConversation(
    tenantId: $tenant->id,
    userId: auth()->id(),
    systemPrompt: 'Ти си корисен асистент.',
    contextType: 'document',
    contextId: $document->id,
    title: 'Анализа на документ',
);

// Испрати порака (автоматски зачувува историја, логира usage, сумаризира)
$response = $manager->sendMessage($conversation, 'Анализирај го документот.');
$response->content; // одговор од AI

// Продолжи разговор
$response2 = $manager->sendMessage($conversation, 'Сумирај ги клучните точки.');

// Вчитај историја (summary + скорешни пораки)
$history = $manager->getHistory($conversation);
```

### Наоѓање конверзација по контекст

```php
use LiveNetworks\LnAiBridge\Models\AiConversation;

// Најди или креирај активна конверзација за даден контекст
$conversation = AiConversation::forContext('document', $document->id, [
    'provider'      => 'claude',
    'model'         => 'claude-sonnet-4-20250514',
    'system_prompt' => 'Ти си корисен асистент.',
    'tenant_id'     => $tenant->id,
    'user_id'       => auth()->id(),
]);
```

## Usage Tracking (Следење потрошувачка)

```php
use LiveNetworks\LnAiBridge\Services\UsageTracker;

$tracker = app(UsageTracker::class);

// Потрошувачка по tenant за период
$usage = $tracker->getTenantUsage(
    tenantId: $tenant->id,
    from: now()->startOfMonth(),
    to: now(),
);
// $usage = ['input_tokens' => 12500, 'output_tokens' => 8300, 'total_tokens' => 20800, 'request_count' => 45]

// Потрошувачка по корисник
$userUsage = $tracker->getUserUsage(userId: auth()->id());
```

### Автоматско логирање

`AiBridgeManager::send()` автоматски логира во `ai_usage_log` ако `usage.tracking_enabled` е `true`.
За пренос на tenant/user контекст при директен send():

```php
$request = AiBridge::prompt()
    ->prompt('Прашање')
    ->meta('tenant_id', $tenant->id)
    ->meta('user_id', auth()->id())
    ->build();

$response = AiBridge::send($request);
```

## Конфигурација

Фајл: `config/ai-bridge.php`

Env варијабли:
- `AI_BRIDGE_PROVIDER` — стандарден провајдер (claude/openai)
- `AI_BRIDGE_CLAUDE_API_KEY` — Anthropic API клуч
- `AI_BRIDGE_OPENAI_API_KEY` — OpenAI API клуч
- `AI_BRIDGE_LOGGING` — логирање (true/false)
- `AI_BRIDGE_USAGE_TRACKING` — следење потрошувачка (true/false)

Conversation поставки:
- `conversation.summarize_threshold` — број несумирани пораки пред сумаризација (default: 20)
- `conversation.keep_recent` — колку скорешни пораки остануваат несумирани (default: 6)
- `conversation.summary_max_tokens` — макс. токени за summary одговор (default: 500)

## Миграции

```bash
php artisan vendor:publish --tag=ai-bridge-migrations
php artisan migrate
```

Табели: `ai_conversations`, `ai_messages`, `ai_conversation_summaries`, `ai_usage_log`

## Принцип

Bridge-от е "dumb pipe" — НЕ знае за бизнис логика.
Контекстот доаѓа од апликацијата: Controller → Models → PromptBuilder → Bridge → Provider → API.
tenant_id е САМО за billing/tracking — bridge-от НЕ филтрира по него.
