<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\DTO\ToolCall;

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
	 * Tool definitions and results are appended when present.
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

		// Tool results from previous executions
		if (!empty($request->toolResults)) {
			// The assistant previously requested tools — reconstruct the response
			if (!empty($request->meta['_assistant_message'])) {
				$messages[] = $request->meta['_assistant_message'];
			}

			foreach ($request->toolResults as $result) {
				$messages[] = [
					'role'         => 'tool',
					'tool_call_id' => $result->toolCallId,
					'content'      => $result->content,
				];
			}
		}

		$payload = [
			'model'       => $this->model(),
			'messages'    => $messages,
			'max_tokens'  => $request->maxTokens,
			'temperature' => $request->temperature,
		];

		// Tool definitions
		if (!empty($request->tools)) {
			$payload['tools'] = array_map(
				fn ($tool) => $tool->toOpenAiFormat(),
				$request->tools,
			);
		}

		return $payload;
	}

	/**
	 * Parse a response from the OpenAI API.
	 *
	 * If finish_reason is "tool_calls", tool calls are parsed from the message.
	 */
	protected function parseResponse(array $data): AiResponse
	{
		$message = $data['choices'][0]['message'] ?? [];
		$content = $message['content'] ?? '';
		$toolCalls = [];

		if (!empty($message['tool_calls'])) {
			foreach ($message['tool_calls'] as $toolCall) {
				$toolCalls[] = ToolCall::fromOpenAiResponse($toolCall);
			}
		}

		return AiResponse::ok(
			content:    $content ?? '',
			provider:   $this->name(),
			model:      $data['model'] ?? $this->model(),
			stopReason: $data['choices'][0]['finish_reason'] ?? null,
			usage:      [
				'input_tokens'  => $data['usage']['prompt_tokens'] ?? 0,
				'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
			],
			raw:        $data,
			toolCalls:  $toolCalls,
		);
	}
}
