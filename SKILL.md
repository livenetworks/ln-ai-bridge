# LN AI Bridge — Claude Code Skill

> Package: `livenetworks/ln-ai-bridge`
> Namespace: `LiveNetworks\LnAiBridge`
> PHP 8.3+ | Laravel 11+/12+ | Guzzle 7.8+ (no external AI SDKs)

## What this is

Unified AI provider abstraction for Laravel apps. Dumb pipe — sends requests to AI providers (Claude, OpenAI, custom), manages conversation history, summarization, and usage tracking. Does NOT contain business logic.

## Architecture
```
App Controller
  → App Models (gather context)
  → App SystemPromptBuilder (compose system prompt)
  → AiBridge::prompt()          ← bridge starts here
    → PromptBuilder (enrich with context, history)
    → AiBridgeManager (resolve provider, delegate)
    → Provider (Claude/OpenAI/custom)
    → External AI API
  → AiResponse                  ← bridge ends here
  → UsageTracker (log tokens per tenant)
```

Context enrichment happens in the APP CONTROLLER, never in the bridge.

## Key classes

| Class | Location | Role |
|---|---|---|
| `AiBridgeManager` | `src/` | Singleton orchestrator. `prompt()`, `send()`, `register()`, `registerTool()`. Auto-resolves tool calls |
| `PromptBuilder` | `src/` | Fluent builder: `.prompt()`, `.system()`, `.context()`, `.history()`, `.temperature()`, `.maxTokens()`, `.meta()`, `.tool()`, `.tools()`, `.build()` |
| `AiRequest` | `src/DTO/` | Readonly: prompt, system, context[], history[], temperature, maxTokens, meta[], tools[], toolResults[] |
| `AiResponse` | `src/DTO/` | Readonly: content, provider, model, success, error, stopReason, usage[], raw[], toolCalls[]. Static `ok()` / `fail()`. Methods `totalTokens()`, `hasToolCalls()`, `isToolUse()` |
| `Message` | `src/DTO/` | Readonly: role, content. Static `user()` / `assistant()` |
| `Tool` | `src/DTO/` | Readonly: name, description, parameters. Static `make()`. Methods `toClaudeFormat()`, `toOpenAiFormat()` |
| `ToolCall` | `src/DTO/` | Readonly: id, name, arguments. Static `fromClaudeResponse()`, `fromOpenAiResponse()` |
| `ToolResult` | `src/DTO/` | Readonly: toolCallId, content, isError. Static `success()`, `error()` |
| `AiProviderInterface` | `src/Contracts/` | Contract: `send(AiRequest): AiResponse`, `name()`, `model()` |
| `ToolExecutorInterface` | `src/Contracts/` | Contract: `execute(ToolCall): ToolResult` — апликацијата го имплементира |
| `ToolRunner` | `src/Services/` | Оркестратор: `register()`, `run(AiResponse): ToolResult[]` |
| `RetryHandler` | `src/Services/` | Generic retry with exponential backoff. Honors Retry-After. Provider-agnostic |
| `AbstractProvider` | `src/Providers/` | Base HTTP logic. Subclasses: `buildHeaders()`, `buildPayload()`, `parseResponse()` |
| `ClaudeProvider` | `src/Providers/` | Anthropic Messages API |
| `OpenAiProvider` | `src/Providers/` | OpenAI Chat Completions API |
| `ConversationManager` | `src/Services/` | `startConversation()`, `sendMessage()`, `getHistory()` |
| `SummarizationService` | `src/Services/` | `shouldSummarize()`, `summarize()` — uses AI to summarize old messages |
| `UsageTracker` | `src/Services/` | `log()`, `getTenantUsage()`, `getUserUsage()` |

## Database tables (publishable migrations)

| Table | Purpose | Key columns |
|---|---|---|
| `ai_conversations` | Conversation sessions | uuid PK, tenant_id (nullable, billing only), user_id, context_type, context_id, provider, model, system_prompt, status, message_count, total_tokens |
| `ai_messages` | Individual messages | uuid PK, conversation_id FK, role, content, tokens, is_summarized |
| `ai_conversation_summaries` | Summarized older messages | uuid PK, conversation_id FK, summary, messages_from, messages_until, messages_count, tokens_saved |
| `ai_usage_log` | Token consumption tracking | auto PK, tenant_id, user_id, provider, model, input_tokens, output_tokens, conversation_id |

## Usage patterns

### Single request (no history)
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
$request = AiBridge::prompt('Generate an ISO 27001 scope statement')
    ->system('You are an ISO documentation assistant.')
    ->context('organization', $org->toArray())
    ->context('existing_docs', $docs->pluck('title'))
    ->temperature(0.3)
    ->build();
```

### Explicit provider
```php
$response = AiBridge::send($request, 'openai');
```

### Multi-turn conversation
```php
use LiveNetworks\LnAiBridge\Services\ConversationManager;

$cm = app(ConversationManager::class);

$conv = $cm->startConversation(
    tenantId: 1,
    userId: 1,
    systemPrompt: 'Reply concisely and clearly.',
);

// First message
$r1 = $cm->sendMessage($conv, 'What is ISO 27001?');

// Second message (AI remembers context)
$r2 = $cm->sendMessage($conv, 'How many controls does it have?');

$conv->fresh()->total_tokens;   // cumulative token usage
$conv->fresh()->message_count;  // 4 (2 user + 2 assistant)
```

### Manual history (without ConversationManager)
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

### Summarization in action
```php
$conv = $cm->startConversation(
    tenantId: 1, userId: 1,
    systemPrompt: 'Reply concisely, 1-2 sentences.',
);

// Send 22 messages (11 questions × 2 = 22, exceeds threshold of 20)
$topics = [
    'What is ISO 27001?', 'What is the difference with ISO 9001?',
    'What is the Statement of Applicability?', 'How many controls are in Annex A?',
    'What is risk assessment?', 'What is an ISMS?',
    'Who performs the internal audit?', 'What is a corrective action?',
    'What is management review?', 'What is continual improvement?',
    'What is a certification body?',
];

foreach ($topics as $topic) {
    $cm->sendMessage($conv, $topic);
}

$conv->refresh();
$summary = $conv->summaries()->latest()->first();
$summary->summary;          // condensed text
$summary->messages_count;   // number of messages summarized
$summary->tokens_saved;     // tokens saved

// After summarization: summary + last 6 messages used as context
$final = $cm->sendMessage($conv, 'Of all topics we discussed, which is most important?');
```

### Tool use (function calling)
```php
// 1. Define tools in the request
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

$response = AiBridge::send($request); // auto-resolves tool calls
$response->content; // final text answer using the document data

// 2. Implement executor in consuming app
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

// 3. Register in AppServiceProvider::boot()
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

// AI may call get_project twice, then get_budget twice — all auto-resolved
$response = AiBridge::send($request);
```

### Usage tracking with meta
```php
$request = AiBridge::prompt('Analyze this data.')
    ->meta('tenant_id', $tenant->id)
    ->meta('user_id', auth()->id())
    ->build();

$response = AiBridge::send($request); // auto-logged to ai_usage_log
```

### Custom provider
```php
// In AppServiceProvider::boot()
AiBridge::register('mistral', MistralProvider::class);

// Use it
$response = AiBridge::send($request, 'mistral');
```

## Context enrichment via XML tags

`PromptBuilder->context(key, value)` wraps data as `<key>value</key>`:
```xml
<document>{"title":"ISO 27001 Policy","sections":["scope","controls"]}</document>
<author>John Smith</author>

User prompt goes here after all context blocks.
```

## Summarization flow

1. After each `sendMessage()`, checks unsummarized count > threshold (default 20)
2. Takes old messages (keeps last 6 fresh)
3. Sends them to AI with "summarize this conversation" prompt
4. Stores summary in `ai_conversation_summaries`
5. Marks old messages as `is_summarized = true`
6. Next request gets: summary + last 6 messages (instead of all 50+)

## Retry (automatic retry with backoff)

`AbstractProvider::send()` wraps the HTTP call in `RetryHandler::execute()`.
RetryHandler is generic — knows nothing about AI specifics.

### Flow
1. HTTP request fails with retryable status code (429, 500, 502, 503, 529)
2. RetryHandler waits: `base_delay_ms × multiplier^attempt` (default: 1s, 2s, 4s)
3. For 429 with Retry-After header → uses that delay instead
4. Retries up to max_retries times
5. All retries exhausted → throws last exception → `AiResponse::fail()`
6. Non-retryable errors (400, 401, 403, 404) → throws immediately, no retry
7. Each retry attempt logged at `Log::warning()` with attempt, status_code, delay_ms

### Config
- `ai-bridge.retry.enabled` (default: true)
- `ai-bridge.retry.max_retries` (default: 3)
- `ai-bridge.retry.base_delay_ms` (default: 1000)
- `ai-bridge.retry.multiplier` (default: 2)
- `ai-bridge.retry.retryable_codes` (default: [429, 500, 502, 503, 529])

## Config (config/ai-bridge.php)
```php
'default' => env('AI_PROVIDER', 'claude'),
'providers' => [
    'claude' => ['driver' => 'claude', 'api_key' => env('...'), 'model' => '...'],
    'openai' => ['driver' => 'openai', 'api_key' => env('...'), 'model' => '...'],
],
'conversation' => [
    'summarize_threshold' => 20,  // after how many unsummarized messages
    'keep_recent' => 6,           // how many to keep fresh
    'summary_max_tokens' => 500,
],
'tools' => [
    'enabled' => true,            // enable/disable tool use
    'max_iterations' => 5,        // max recursive tool call rounds
],
'retry' => [
    'enabled' => true,            // automatic retry on transient failures
    'max_retries' => 3,           // max attempts
    'base_delay_ms' => 1000,      // base delay (1s, 2s, 4s with multiplier 2)
    'multiplier' => 2,            // exponential backoff multiplier
    'retryable_codes' => [429, 500, 502, 503, 529],
],
'usage' => [
    'tracking_enabled' => true,
],
```

## Rules for Claude Code

- NEVER put business logic in the bridge (no ISO knowledge, no document types, no tenant scoping)
- NEVER access app models from bridge code
- Context flows ONE direction: App → Bridge → Provider
- AiRequest and AiResponse are immutable (readonly)
- Provider instances are cached (singleton per driver name)
- All provider API errors are caught and returned as AiResponse::fail()
- tenant_id in bridge is ONLY for billing/tracking, never for filtering
- Summarization is an AI call — it uses AiBridgeManager internally
- Messages are immutable (no updated_at, only created_at)
- Usage log is append-only
- Tool DTOs (Tool, ToolCall, ToolResult) are readonly
- ToolExecutorInterface is in Contracts/ — the consuming app implements it
- Tool execution errors return ToolResult::error(), never throw exceptions
- Bridge auto-resolves tool calls (caller doesn't need to handle them)
- max_iterations config prevents infinite tool call loops
- Token usage is logged for EVERY iteration, not just the final one
- RetryHandler is generic (no AI knowledge) — only catches GuzzleException
- Retry is transparent to callers — they get final AiResponse only
- 529 is Anthropic-specific "overloaded" code — treated as retryable

## App integration pattern

The consuming app (DocuFlow, AuditBase) is responsible for:
- SystemPromptBuilder (composing multi-layer system prompts)
- Tenant scoping (global scope on bridge models)
- Adding tenant_id column if not present (own migration)
- AiController with business logic
- Extending bridge models with BelongsToTenant or similar traits
- ISO/domain-specific context gathering from app models