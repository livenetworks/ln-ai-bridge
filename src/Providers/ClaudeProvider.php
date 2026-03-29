<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\DTO\ToolCall;

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
	 * Tool definitions and results are appended when present.
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

		// Tool results from previous executions — append assistant + user tool_result messages
		if (!empty($request->toolResults)) {
			// The assistant previously requested tools — reconstruct from raw
			if (!empty($request->meta['_assistant_content'])) {
				$messages[] = [
					'role'    => 'assistant',
					'content' => $request->meta['_assistant_content'],
				];
			}

			$toolResultBlocks = [];
			foreach ($request->toolResults as $result) {
				$block = [
					'type'        => 'tool_result',
					'tool_use_id' => $result->toolCallId,
					'content'     => $result->content,
				];
				if ($result->isError) {
					$block['is_error'] = true;
				}
				$toolResultBlocks[] = $block;
			}

			$messages[] = [
				'role'    => 'user',
				'content' => $toolResultBlocks,
			];
		}

		$payload = [
			'model'       => $this->model(),
			'messages'    => $messages,
			'max_tokens'  => $request->maxTokens,
			'temperature' => $request->temperature,
		];

		if ($request->system !== null) {
			$payload['system'] = $request->system;
		}

		// Tool definitions
		if (!empty($request->tools)) {
			$payload['tools'] = array_map(
				fn ($tool) => $tool->toClaudeFormat(),
				$request->tools,
			);
		}

		return $payload;
	}

	/**
	 * Parse a response from the Claude API.
	 *
	 * If stop_reason is "tool_use", tool calls are parsed from content blocks.
	 * Text blocks are collected into content.
	 */
	protected function parseResponse(array $data): AiResponse
	{
		$textParts = [];
		$toolCalls = [];

		foreach ($data['content'] ?? [] as $block) {
			if ($block['type'] === 'text') {
				$textParts[] = $block['text'];
			} elseif ($block['type'] === 'tool_use') {
				$toolCalls[] = ToolCall::fromClaudeResponse($block);
			}
		}

		$content = implode("\n", $textParts);

		return AiResponse::ok(
			content:    $content,
			provider:   $this->name(),
			model:      $data['model'] ?? $this->model(),
			stopReason: $data['stop_reason'] ?? null,
			usage:      [
				'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
				'output_tokens' => $data['usage']['output_tokens'] ?? 0,
			],
			raw:        $data,
			toolCalls:  $toolCalls,
		);
	}
}
