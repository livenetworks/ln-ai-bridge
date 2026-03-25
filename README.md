# LN AI Bridge

AI provider abstraction layer за Laravel апликации. Унифициран интерфејс за Claude, OpenAI, и други AI сервиси — директно преку Guzzle HTTP, без external SDK-ови.

## Инсталација

```bash
composer require livenetworks/ln-ai-bridge
```

Publish конфигурација:

```bash
php artisan vendor:publish --tag=ai-bridge-config
```

## Конфигурација

Додадете ги API клучевите во `.env`:

```env
AI_BRIDGE_PROVIDER=claude
AI_BRIDGE_CLAUDE_API_KEY=your-claude-key
AI_BRIDGE_OPENAI_API_KEY=your-openai-key
```

## Користење

### Основно барање

```php
use LiveNetworks\LnAiBridge\Facades\AiBridge;

$response = AiBridge::prompt()
    ->system('Ти си корисен асистент.')
    ->prompt('Што е Laravel?')
    ->temperature(0.5)
    ->maxTokens(512)
    ->build();

$result = AiBridge::send($response);

echo $result->content;
```

### Со контекст (XML тагови)

```php
$request = AiBridge::prompt()
    ->system('Одговори на клиентот.')
    ->context('customer_name', 'Јован')
    ->context('order_id', '12345')
    ->prompt('Напиши одговор за статусот на нарачката.')
    ->build();

$result = AiBridge::send($request);
```

Контекстот се вметнува како XML тагови во prompt-от:
```
<customer_name>Јован</customer_name>
<order_id>12345</order_id>

Напиши одговор за статусот на нарачката.
```

### Multi-turn конверзација

```php
use LiveNetworks\LnAiBridge\DTO\Message;

$request = AiBridge::prompt()
    ->system('Ти си техничка поддршка.')
    ->history([
        Message::user('Не можам да се логирам.'),
        Message::assistant('Дали пробавте да го ресетирате лозинката?'),
    ])
    ->prompt('Да, но не добивам email.')
    ->build();

$result = AiBridge::send($request);
```

### Избор на провајдер

```php
// Стандарден провајдер (од config)
$result = AiBridge::send($request);

// Експлицитен провајдер
$result = AiBridge::send($request, 'openai');
```

### Custom провајдер

```php
use LiveNetworks\LnAiBridge\Facades\AiBridge;

AiBridge::register('mistral', MistralProvider::class);
```

## Одговор (AiResponse)

```php
$result->content;      // Текст на одговорот
$result->success;      // bool
$result->provider;     // "claude" | "openai"
$result->model;        // "claude-sonnet-4-20250514"
$result->error;        // null | error message
$result->stopReason;   // "end_turn" | "max_tokens" | ...
$result->totalTokens(); // input + output tokens
$result->usage;        // ['input_tokens' => ..., 'output_tokens' => ...]
$result->raw;          // Суров API одговор
```

## Архитектура

Bridge-от е **"dumb pipe"** — не знае за бизнис логика. Контекстот доаѓа од апликацијата:

```
Controller → Models → PromptBuilder → Bridge → Provider → API
```

## Лиценца

Proprietary — LiveNetworks.
