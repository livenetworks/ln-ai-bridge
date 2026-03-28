<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Provider for Anthropic Claude (Messages API).
 *
 * Communicates directly with the Anthropic API via Guzzle.
 * Supports multi-turn conversations and system messages.
 */
class ClaudeProvider extends AbstractProvider
{
	public function name(): string
	{
		return 'claude';
	}

	protected function endpoint(): string
	{
		return '/v1/messages';
	}

	protected function buildHeaders(): array
	{
		return [
			'x-api-key'         => $this->config['api_key'],
			'anthropic-version' => $this->config['version'] ?? '2023-06-01',
			'content-type'      => 'application/json',
		];
	}

	/**
	 * Build payload for the Anthropic Messages API.
	 *
	 * History and current prompt are merged into the messages array.
	 * The system message is sent separately in the "system" field.
	 */
	protected function buildPayload(AiRequest $request): array
	{
		$messages = [];

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

		$payload = [
			'model'       => $this->model(),
			'messages'    => $messages,
			'max_tokens'  => $request->maxTokens,
			'temperature' => $request->temperature,
		];

		if ($request->system !== null) {
			$payload['system'] = $request->system;
		}

		return $payload;
	}

	protected function parseResponse(array $data): AiResponse
	{
		$content = $data['content'][0]['text'] ?? '';

		return AiResponse::ok(
			content:    $content,
			provider:   $this->name(),
			model:      $data['model'] ?? $this->model(),
			stopReason: $data['stop_reason'] ?? null,
			usage:      [
				'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
				'output_tokens' => $data['usage']['output_tokens'] ?? 0,
			],
			raw: $data,
		);
	}
}
