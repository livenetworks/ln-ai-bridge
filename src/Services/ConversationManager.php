<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Services;

use Illuminate\Support\Facades\DB;
use LiveNetworks\LnAiBridge\AiBridgeManager;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\DTO\Message;
use LiveNetworks\LnAiBridge\Models\AiConversation;
use LiveNetworks\LnAiBridge\Models\AiMessage;

/**
 * Service for managing AI conversations.
 *
 * Orchestrates the full lifecycle: creating conversations, sending messages,
 * loading history (with summary), and automatic summarization.
 * The bridge remains a "dumb pipe" — business logic stays in the application.
 */
class ConversationManager
{
	public function __construct(
		private AiBridgeManager $manager,
		private SummarizationService $summarization,
		private UsageTracker $usageTracker,
	) {}

	/**
	 * Start a new conversation.
	 */
	public function startConversation(
		?int $tenantId = null,
		?int $userId = null,
		?string $systemPrompt = null,
		?string $contextType = null,
		int|string|null $contextId = null,
		?string $title = null,
		?string $provider = null,
		?string $model = null,
	): AiConversation {
		$provider = $provider ?? config('ai-bridge.default', 'claude');
		$model = $model ?? config("ai-bridge.providers.{$provider}.model", '');

		return AiConversation::create([
			'tenant_id'     => $tenantId,
			'user_id'       => $userId,
			'system_prompt' => $systemPrompt,
			'context_type'  => $contextType,
			'context_id'    => $contextId,
			'title'         => $title,
			'provider'      => $provider,
			'model'         => $model,
		]);
	}

	/**
	 * Send a message in an existing conversation.
	 *
	 * Saves the user message, loads history, sends to AI,
	 * saves the assistant response, updates counters,
	 * and checks whether summarization is needed.
	 *
	 * @param  array<string, mixed> $meta Additional metadata for the request
	 */
	public function sendMessage(
		AiConversation $conversation,
		string $userMessage,
		array $meta = [],
	): AiResponse {
		// Save the user message
		$conversation->messages()->create([
			'role'    => 'user',
			'content' => $userMessage,
		]);

		// Load history (summary + recent messages)
		$history = $this->getHistory($conversation);

		// Build system prompt (original + latest summary if available)
		$systemPrompt = $this->buildSystemPrompt($conversation);

		// Build and send the request
		$builder = $this->manager->prompt()
			->prompt($userMessage)
			->history($history)
			->meta('skip_auto_tracking', true);

		if ($systemPrompt !== null) {
			$builder->system($systemPrompt);
		}

		foreach ($meta as $key => $value) {
			$builder->meta($key, $value);
		}

		$request = $builder->build();
		$response = $this->manager->send($request, $conversation->provider);

		if ($response->success) {
			DB::transaction(function () use ($conversation, $response) {
				// Save the assistant response
				$conversation->messages()->create([
					'role'    => 'assistant',
					'content' => $response->content,
					'tokens'  => $response->totalTokens(),
					'metadata' => [
						'stop_reason' => $response->stopReason,
					],
				]);

				// Update conversation counters
				$conversation->increment('message_count', 2);
				$conversation->increment('total_tokens', $response->totalTokens());
			});

			// Log usage
			$this->usageTracker->log(
				response: $response,
				tenantId: $conversation->tenant_id,
				userId: $conversation->user_id,
				conversationId: $conversation->id,
			);

			// Check for summarization (never let an error break the main flow)
			try {
				if ($this->summarization->shouldSummarize($conversation)) {
					$this->summarization->summarize(
						$conversation,
						$conversation->tenant_id,
						$conversation->user_id,
					);
				}
			} catch (\Throwable) {
				// Summarization is best-effort — never interrupt the main operation
			}
		}

		return $response;
	}

	/**
	 * Return message history as DTO Message[] for PromptBuilder.
	 *
	 * Loads unsummarized messages (excluding the last user message which is the prompt).
	 * The summary is added to the system prompt, not here.
	 *
	 * @return Message[]
	 */
	public function getHistory(AiConversation $conversation, int $limit = 20): array
	{
		$messages = $conversation->messages()
			->unsummarized()
			->orderBy('created_at')
			->limit($limit)
			->get();

		// Remove the last user message (it's the prompt, not history)
		if ($messages->isNotEmpty() && $messages->last()->role === 'user') {
			$messages = $messages->slice(0, -1);
		}

		return $messages->map(fn (AiMessage $msg) => $msg->toDto())->values()->all();
	}

	/**
	 * Build system prompt with the latest summary included (if available).
	 */
	private function buildSystemPrompt(AiConversation $conversation): ?string
	{
		$latestSummary = $conversation->summaries()
			->orderByDesc('created_at')
			->first();

		if ($conversation->system_prompt === null && $latestSummary === null) {
			return null;
		}

		$parts = [];

		if ($conversation->system_prompt !== null) {
			$parts[] = $conversation->system_prompt;
		}

		if ($latestSummary !== null) {
			$parts[] = "<conversation_summary>{$latestSummary->summary}</conversation_summary>";
		}

		return implode("\n\n", $parts);
	}
}
