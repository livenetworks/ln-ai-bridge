<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Tool call requested by the AI.
 *
 * When the AI decides it needs information from the backend,
 * it returns a tool call instead of a text response.
 */
readonly class ToolCall
{
	/**
	 * @param  string               $id        Unique call ID (from the provider)
	 * @param  string               $name      Name of the requested tool
	 * @param  array<string, mixed> $arguments Parsed arguments from the AI
	 */
	public function __construct(
		public string $id,
		public string $name,
		public array  $arguments,
	) {}

	/**
	 * Create from an Anthropic Claude response block.
	 *
	 * Claude returns: { type: "tool_use", id: "...", name: "...", input: {...} }
	 *
	 * @param  array<string, mixed> $block Content block with type "tool_use"
	 */
	public static function fromClaudeResponse(array $block): self
	{
		return new self(
			id:        $block['id'],
			name:      $block['name'],
			arguments: $block['input'] ?? [],
		);
	}

	/**
	 * Create from an OpenAI response tool call.
	 *
	 * OpenAI returns: { id: "...", type: "function", function: { name: "...", arguments: "{...}" } }
	 *
	 * @param  array<string, mixed> $toolCall Element from the tool_calls array
	 */
	public static function fromOpenAiResponse(array $toolCall): self
	{
		$arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];

		return new self(
			id:        $toolCall['id'],
			name:      $toolCall['function']['name'],
			arguments: $arguments,
		);
	}
}
