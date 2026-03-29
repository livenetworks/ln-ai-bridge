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

### Single request

```php
use LiveNetworks\LnAiBridge\Facades\AiBridge;

$request = AiBridge::prompt('Summarize the key benefits of cloud computing in 3 bullet points.')
    ->system('You are a concise technical writer.')
    ->temperature(0.5)
    ->build();

$response = AiBridge::send($request);

$response->success;   // true
$response->content;   // "• Scalability..."
$response->provider;  // "claude"
$response->model;     // "claude-sonnet-4-20250514"
$response->usage;     // ['input_tokens' => 42, 'output_tokens' => 85]
$response->error;     // null
```

### With context injection

```php
$request = AiBridge::prompt('Write a greeting for the customer.')
    ->system('You are a helpful assistant.')
    ->context('customer_name', 'John')
    ->context('order_total', '$149.99')
    ->build();
```

Context pairs are injected as XML tags into the prompt:

```
<customer_name>John</customer_name>
<order_total>$149.99</order_total>

Write a greeting for the customer.
```

### Explicit provider

```php
// Use a specific provider instead of the default
$response = AiBridge::send($request, 'openai');
```

## Conversations

Multi-turn conversations with persistent history, automatic summarization, and usage tracking:

```php
use LiveNetworks\LnAiBridge\Services\ConversationManager;

$cm = app(ConversationManager::class);

// Start a conversation
$conversation = $cm->startConversation(
    tenantId: 1,
    userId: 1,
    systemPrompt: 'Reply concisely and clearly.',
);

// First message
$r1 = $cm->sendMessage($conversation, 'What is ISO 27001?');

// Second message (the AI remembers context from the first)
$r2 = $cm->sendMessage($conversation, 'How many controls does it have?');

$conversation->fresh()->total_tokens;   // cumulative token usage
$conversation->fresh()->message_count;  // 4 (2 user + 2 assistant)
```

All messages are persisted to the database. History is automatically loaded and included with each request.

### Manual history (without ConversationManager)

For apps that manage their own message storage:

```php
use LiveNetworks\LnAiBridge\DTO\Message;

$request = AiBridge::prompt('What about point 3?')
    ->system('You are a document assistant.')
    ->history([
        Message::user('Generate a security policy'),
        Message::assistant('Here is the policy with 5 key points...'),
    ])
    ->build();

$response = AiBridge::send($request);
```

### Conversation with context binding

```php
$conversation = $cm->startConversation(
    tenantId: $tenant->id,
    userId: auth()->id(),
    systemPrompt: 'You are a document analysis assistant.',
    contextType: 'document',
    contextId: $document->id,
    title: 'Document analysis',
);
```

## Summarization

When a conversation exceeds the configured message threshold, older messages are automatically summarized via an AI call. The summary replaces the old messages in context, keeping the conversation within token limits.

```php
$cm = app(ConversationManager::class);

$conv = $cm->startConversation(
    tenantId: 1,
    userId: 1,
    systemPrompt: 'Reply concisely, 1-2 sentences.',
);

// Send 22 messages (11 questions × 2 = 22, exceeds threshold of 20)
$topics = [
    'What is ISO 27001?',
    'What is the difference with ISO 9001?',
    'What is the Statement of Applicability?',
    'How many controls are in Annex A?',
    'What is risk assessment?',
    'What is an ISMS?',
    'Who performs the internal audit?',
    'What is a corrective action?',
    'What is management review?',
    'What is continual improvement?',
    'What is a certification body?',
];

foreach ($topics as $topic) {
    $cm->sendMessage($conv, $topic);
}

$conv->refresh();

// Summary was automatically created
$summary = $conv->summaries()->latest()->first();
$summary->summary;          // condensed text of the conversation
$summary->messages_count;   // number of messages summarized
$summary->tokens_saved;     // tokens saved by summarizing

// Messages after summarization use: summary + last 6 messages as context
$final = $cm->sendMessage($conv, 'Of all the topics we discussed, which is most important?');
$final->content; // AI responds with full context awareness
```

Configure in `config/ai-bridge.php`:

```php
'conversation' => [
    'summarize_threshold' => 20,  // Trigger after this many unsummarized messages
    'keep_recent'         => 6,   // Keep the last N messages unsummarized
    'summary_max_tokens'  => 500, // Max tokens for the summary response
],
```

## Tool Use (Function Calling)

The AI can request data from the backend via tools. Tool calls are executed automatically — the caller receives only the final text response.

### 1. Define tools in the request

```php
$request = AiBridge::prompt()
    ->system('You are a document assistant.')
    ->prompt('What does document DOC-123 contain?')
    ->tool('get_document_content', 'Retrieves document content by ID', [
        'properties' => [
            'document_id' => ['type' => 'string', 'description' => 'The document ID'],
        ],
        'required' => ['document_id'],
    ])
    ->build();

$response = AiBridge::send($request); // tool calls are auto-resolved
echo $response->content; // final text answer using the document data
```

### 2. Implement a tool executor

```php
use LiveNetworks\LnAiBridge\Contracts\ToolExecutorInterface;
use LiveNetworks\LnAiBridge\DTO\ToolCall;
use LiveNetworks\LnAiBridge\DTO\ToolResult;

class GetDocumentContentExecutor implements ToolExecutorInterface
{
    public function execute(ToolCall $call): ToolResult
    {
        $doc = Document::find($call->arguments['document_id']);

        if (!$doc) {
            return ToolResult::error($call->id, 'Document not found');
        }

        return ToolResult::success($call->id, $doc->content);
    }
}
```

### 3. Register executors

```php
// In AppServiceProvider::boot()
AiBridge::registerTool('get_document_content', new GetDocumentContentExecutor());
```

### Multiple tools in one request

```php
$request = AiBridge::prompt()
    ->system('You are a project assistant.')
    ->prompt('Compare the budgets of projects Alpha and Beta.')
    ->tool('get_project', 'Get project details by name', [
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Project name'],
        ],
        'required' => ['name'],
    ])
    ->tool('get_budget', 'Get budget for a project', [
        'properties' => [
            'project_id' => ['type' => 'integer', 'description' => 'Project ID'],
        ],
        'required' => ['project_id'],
    ])
    ->build();

// The AI may call get_project twice, then get_budget twice,
// across multiple iterations — all handled automatically.
$response = AiBridge::send($request);
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

Pass tenant/user context for tracking on direct `send()` calls:

```php
$request = AiBridge::prompt('Analyze this data.')
    ->meta('tenant_id', $tenant->id)
    ->meta('user_id', auth()->id())
    ->build();

$response = AiBridge::send($request);
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
| `ai-bridge.retry.enabled` | `true` | Enable automatic retry |
| `ai-bridge.retry.max_retries` | `3` | Max retry attempts |
| `ai-bridge.retry.base_delay_ms` | `1000` | Base delay in milliseconds |
| `ai-bridge.retry.multiplier` | `2` | Exponential backoff multiplier |
| `ai-bridge.retry.retryable_codes` | `[429,500,502,503,529]` | HTTP status codes to retry |

## Retry

API requests are automatically retried on transient failures with exponential backoff. The caller receives only the final response — retries are transparent.

- **Retryable:** 429 (rate limit), 500, 502, 503, 529 (Anthropic overloaded)
- **Not retryable:** 400, 401, 403, 404 (client errors fail immediately)
- **Backoff:** `base_delay_ms × multiplier^attempt` → default 1s, 2s, 4s
- **Retry-After:** honored on 429 responses (takes priority over backoff)
- **Logging:** each retry attempt is logged at `warning` level

Configure in `config/ai-bridge.php`:

```php
'retry' => [
    'enabled'         => true,
    'max_retries'     => 3,
    'base_delay_ms'   => 1000,
    'multiplier'      => 2,
    'retryable_codes' => [429, 500, 502, 503, 529],
],
```

Disable via `.env`:

```env
AI_BRIDGE_RETRY_ENABLED=false
```

## License

MIT License — Live Networks DOOEL.
