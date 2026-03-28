# LN AI Bridge

AI provider abstraction layer for Laravel. Unified interface for Claude, OpenAI, and custom providers — direct Guzzle HTTP, no external SDKs.

## Installation

```bash
composer require livenetworks/ln-ai-bridge
```

```bash
php artisan vendor:publish --tag=ai-bridge-config
php artisan vendor:publish --tag=ai-bridge-migrations
php artisan migrate
```

## Configuration

Add to your `.env`:

```env
AI_BRIDGE_PROVIDER=claude
AI_BRIDGE_CLAUDE_API_KEY=your-claude-key
AI_BRIDGE_OPENAI_API_KEY=your-openai-key
```

## Quick Start

```php
use LiveNetworks\LnAiBridge\Facades\AiBridge;

$request = AiBridge::prompt()
    ->system('You are a helpful assistant.')
    ->context('customer_name', 'John')
    ->prompt('Write a greeting for the customer.')
    ->build();

$response = AiBridge::send($request);

echo $response->content;
```

Context pairs are injected as XML tags into the prompt:

```
<customer_name>John</customer_name>

Write a greeting for the customer.
```

## Conversations

Multi-turn conversations with persistent history, managed via `ConversationManager`:

```php
use LiveNetworks\LnAiBridge\Services\ConversationManager;

$manager = app(ConversationManager::class);

$conversation = $manager->startConversation(
    tenantId: 1,
    userId: 42,
    systemPrompt: 'You are a support agent.',
    title: 'Billing inquiry',
);

$response = $manager->sendMessage($conversation, 'I need help with my invoice.');
echo $response->content;

$response = $manager->sendMessage($conversation, 'Can you check order #12345?');
echo $response->content;
```

All messages are persisted to the database. History is automatically loaded and included with each request.

## Summarization

When a conversation exceeds the configured message threshold, older messages are automatically summarized via an AI call. The summary replaces the old messages in context, keeping the conversation within token limits.

Configure in `config/ai-bridge.php`:

```php
'conversation' => [
    'summarize_threshold' => 20,  // Trigger after this many unsummarized messages
    'keep_recent'         => 6,   // Keep the last N messages unsummarized
    'summary_max_tokens'  => 500, // Max tokens for the summary response
],
```

## Usage Tracking

Every AI request is automatically logged to `ai_usage_log` with input/output token counts, provider, and model. Query aggregated usage per tenant or user:

```php
use LiveNetworks\LnAiBridge\Services\UsageTracker;

$tracker = app(UsageTracker::class);

$usage = $tracker->getTenantUsage(
    tenantId: 1,
    from: now()->startOfMonth(),
);

// Returns: ['input_tokens' => ..., 'output_tokens' => ..., 'total_tokens' => ..., 'request_count' => ...]
```

Disable tracking in `.env`:

```env
AI_BRIDGE_USAGE_TRACKING=false
```

## Adding Custom Providers

**1. Create the provider class** extending `AbstractProvider`:

```php
use LiveNetworks\LnAiBridge\Providers\AbstractProvider;
use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

class MistralProvider extends AbstractProvider
{
    public function name(): string { return 'mistral'; }
    public function model(): string { return $this->config['model']; }
    protected function endpoint(): string { return '/v1/chat/completions'; }
    protected function buildHeaders(): array { /* ... */ }
    protected function buildPayload(AiRequest $request): array { /* ... */ }
    protected function parseResponse(array $data): AiResponse { /* ... */ }
}
```

**2. Register it** in a service provider:

```php
AiBridge::register('mistral', MistralProvider::class);
```

**3. Add config** to `config/ai-bridge.php` providers array:

```php
'mistral' => [
    'api_key'  => env('AI_BRIDGE_MISTRAL_API_KEY'),
    'model'    => env('AI_BRIDGE_MISTRAL_MODEL', 'mistral-large-latest'),
    'base_url' => 'https://api.mistral.ai',
],
```

## Configuration Reference

| Key | Default | Description |
|-----|---------|-------------|
| `ai-bridge.default` | `claude` | Default provider |
| `ai-bridge.providers.claude.api_key` | — | Anthropic API key |
| `ai-bridge.providers.claude.model` | `claude-sonnet-4-20250514` | Claude model |
| `ai-bridge.providers.claude.base_url` | `https://api.anthropic.com` | Claude API base URL |
| `ai-bridge.providers.claude.version` | `2023-06-01` | Anthropic API version |
| `ai-bridge.providers.openai.api_key` | — | OpenAI API key |
| `ai-bridge.providers.openai.model` | `gpt-4o` | OpenAI model |
| `ai-bridge.providers.openai.base_url` | `https://api.openai.com` | OpenAI API base URL |
| `ai-bridge.logging` | `false` | Enable request/response logging |
| `ai-bridge.defaults.temperature` | `0.4` | Default temperature |
| `ai-bridge.defaults.max_tokens` | `8192` | Default max tokens |
| `ai-bridge.defaults.timeout` | `60` | HTTP timeout (seconds) |
| `ai-bridge.conversation.summarize_threshold` | `20` | Messages before auto-summarization |
| `ai-bridge.conversation.keep_recent` | `6` | Recent messages to keep unsummarized |
| `ai-bridge.conversation.summary_max_tokens` | `500` | Max tokens for summary |
| `ai-bridge.usage.tracking_enabled` | `true` | Enable usage tracking |

## License

Proprietary — Live Networks DOOEL.
