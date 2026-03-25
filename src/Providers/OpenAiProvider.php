<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Провајдер за OpenAI (Chat Completions API).
 *
 * Комуницира директно со OpenAI API преку Guzzle.
 * Поддржува multi-turn конверзации и системски пораки.
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
	 * Гради payload за OpenAI Chat Completions API.
	 *
	 * Системската порака е прв елемент во messages низата.
	 * Историјата и тековниот prompt следат по неа.
	 */
	protected function buildPayload(AiRequest $request): array
	{
		$messages = [];

		// Системска порака (ако има)
		if ($request->system !== null) {
			$messages[] = [
				'role'    => 'system',
				'content' => $request->system,
			];
		}

		// Историја на претходни пораки (multi-turn)
		foreach ($request->history as $message) {
			$messages[] = [
				'role'    => $message->role,
				'content' => $message->content,
			];
		}

		// Тековен prompt како последна корисничка порака
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
