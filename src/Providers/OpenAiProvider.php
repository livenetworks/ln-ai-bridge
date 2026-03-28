<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Provider for OpenAI (Chat Completions API).
 *
 * Communicates directly with the OpenAI API via Guzzle.
 * Supports multi-turn conversations and system messages.
 */
class OpenAiProvider extends AbstractProvider
{
	public function name(): string
	{
		return 'openai';
	}

	protected function endpoint(): string
	{
		return '/v1/chat/completions';
	}

	protected function buildHeaders(): array
	{
		return [
			'Authorization' => 'Bearer ' . $this->config['api_key'],
			'Content-Type'  => 'application/json',
		];
	}

	/**
	 * Build payload for the OpenAI Chat Completions API.
	 *
	 * The system message is the first element in the messages array.
	 * History and current prompt follow after it.
	 */
	protected function buildPayload(AiRequest $request): array
	{
		$messages = [];

		// System message (if provided)
		if ($request->system !== null) {
			$messages[] = [
				'role'    => 'system',
				'content' => $request->system,
			];
		}

		// Previous message history (multi-turn)
		foreach ($request->history as $message) {
			$messages[] = [
				'role'    => $message->role,
				'content' => $message->content,
			];
		}

		// Current prompt as the last user message
		$messages[] = [
			'role'    => 'user',
			'content' => $request->prompt,
		];

		return [
			'model'       => $this->model(),
			'messages'    => $messages,
			'max_tokens'  => $request->maxTokens,
			'temperature' => $request->temperature,
		];
	}

	protected function parseResponse(array $data): AiResponse
	{
		$content = $data['choices'][0]['message']['content'] ?? '';

		return AiResponse::ok(
			content:    $content,
			provider:   $this->name(),
			model:      $data['model'] ?? $this->model(),
			stopReason: $data['choices'][0]['finish_reason'] ?? null,
			usage:      [
				'input_tokens'  => $data['usage']['prompt_tokens'] ?? 0,
				'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
			],
			raw: $data,
		);
	}
}
