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

## Принцип

Bridge-от е "dumb pipe" — НЕ знае за бизнис логика.
Контекстот доаѓа од апликацијата: Controller → Models → PromptBuilder → Bridge → Provider → API.
