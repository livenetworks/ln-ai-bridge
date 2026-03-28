<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Services;

use LiveNetworks\LnAiBridge\AiBridgeManager;
use LiveNetworks\LnAiBridge\Models\AiConversation;
use LiveNetworks\LnAiBridge\Models\AiConversationSummary;
use LiveNetworks\LnAiBridge\Models\AiMessage;

/**
 * Service for automatic summarization of older messages.
 *
 * When the number of unsummarized messages exceeds the threshold, the service
 * summarizes the older messages via an AI call and stores the result.
 * Incremental — if a previous summary exists, it is included as well.
 */
class SummarizationService
{
	public function __construct(
		private AiBridgeManager $manager,
		private UsageTracker $usageTracker,
	) {}

	/**
	 * Check whether the conversation needs summarization.
	 */
	public function shouldSummarize(AiConversation $conversation): bool
	{
		$threshold = (int) config('ai-bridge.conversation.summarize_threshold', 20);

		return $conversation->messages()->unsummarized()->count() >= $threshold;
	}

	/**
	 * Summarize older messages in the conversation.
	 *
	 * Takes the unsummarized messages (except the last N which remain fresh),
	 * sends a prompt to AI for summarization, stores the result,
	 * and marks the messages as summarized.
	 */
	public function summarize(
		AiConversation $conversation,
		?int $tenantId = null,
		?int $userId = null,
	): ?AiConversationSummary {
		$keepRecent = (int) config('ai-bridge.conversation.keep_recent', 6);

		$unsummarized = $conversation->messages()
			->unsummarized()
			->orderBy('created_at')
			->get();

		if ($unsummarized->count() <= $keepRecent) {
			return null;
		}

		// Older messages to summarize (excluding the last N)
		$toSummarize = $unsummarized->slice(0, $unsummarized->count() - $keepRecent);

		if ($toSummarize->count() < 2) {
			return null;
		}

		// Build the text for summarization
		$conversationText = $this->formatMessages($toSummarize);

		// If a previous summary exists, include it (incremental)
		$previousSummary = $conversation->summaries()
			->orderByDesc('created_at')
			->first();

		$systemPrompt = 'Summarize the following conversation briefly. Preserve key decisions, facts, and context. Respond ONLY with the summary, without additional commentary.';

		$promptText = '';
		if ($previousSummary !== null) {
			$promptText .= "<previous_summary>{$previousSummary->summary}</previous_summary>\n\n";
		}
		$promptText .= "<messages>\n{$conversationText}\n</messages>";

		$summaryMaxTokens = (int) config('ai-bridge.conversation.summary_max_tokens', 500);

		$request = $this->manager->prompt()
			->system($systemPrompt)
			->prompt($promptText)
			->maxTokens($summaryMaxTokens)
			->meta('skip_auto_tracking', true)
			->build();

		$response = $this->manager->send($request, $conversation->provider);

		if (! $response->success) {
			return null;
		}

		// Calculate saved tokens (approximate — sum of tokens from summarized messages)
		$tokensSaved = $toSummarize->sum('tokens') ?? 0;

		$summary = AiConversationSummary::create([
			'conversation_id' => $conversation->id,
			'summary'         => $response->content,
			'messages_from'   => $toSummarize->first()->created_at,
			'messages_until'  => $toSummarize->last()->created_at,
			'messages_count'  => $toSummarize->count(),
			'tokens_saved'    => (int) $tokensSaved,
		]);

		// Mark messages as summarized
		AiMessage::whereIn('id', $toSummarize->pluck('id'))
			->update(['is_summarized' => true]);

		// Log token usage for the summarization call itself
		$this->usageTracker->log(
			response: $response,
			tenantId: $tenantId,
			userId: $userId,
			conversationId: $conversation->id,
		);

		return $summary;
	}

	/**
	 * Format messages into text for summarization.
	 */
	private function formatMessages($messages): string
	{
		return $messages->map(function (AiMessage $message) {
			return "[{$message->role}]: {$message->content}";
		})->implode("\n\n");
	}
}
