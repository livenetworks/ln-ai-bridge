<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Response from an AI provider.
 *
 * Unified format for responses from all providers.
 * Contains content, meta-information, and raw response for debugging.
 */
readonly class AiResponse
{
	/**
	 * @param  string      $content    Text content of the response
	 * @param  string      $provider   Provider name (claude, openai)
	 * @param  string      $model      Model that generated the response
	 * @param  bool        $success    Whether the request was successful
	 * @param  string|null $error      Error message (if any)
	 * @param  string|null $stopReason Reason generation stopped
	 * @param  array<string, int> $usage Token usage (input_tokens, output_tokens)
	 * @param  array<string, mixed> $raw Raw API response
	 * @param  ToolCall[]  $toolCalls  Tool calls requested by the AI
	 */
	public function __construct(
		public string  $content,
		public string  $provider,
		public string  $model,
		public bool    $success,
		public ?string $error = null,
		public ?string $stopReason = null,
		public array   $usage = [],
		public array   $raw = [],
		public array   $toolCalls = [],
	) {}

	/**
	 * Whether the AI requested tool calls.
	 */
	public function hasToolCalls(): bool
	{
		return !empty($this->toolCalls);
	}

	/**
	 * Whether the response is a tool use (alias for hasToolCalls, more readable).
	 */
	public function isToolUse(): bool
	{
		return $this->hasToolCalls();
	}

	/**
	 * Create a successful response.
	 *
	 * @param  array<string, int>   $usage
	 * @param  array<string, mixed> $raw
	 * @param  ToolCall[]           $toolCalls
	 */
	public static function ok(
		string  $content,
		string  $provider,
		string  $model,
		?string $stopReason = null,
		array   $usage = [],
		array   $raw = [],
		array   $toolCalls = [],
	): self {
		return new self(
			content:    $content,
			provider:   $provider,
			model:      $model,
			success:    true,
			stopReason: $stopReason,
			usage:      $usage,
			raw:        $raw,
			toolCalls:  $toolCalls,
		);
	}

	/**
	 * Create a failed response (error).
	 *
	 * @param  array<string, mixed> $raw
	 */
	public static function fail(
		string $error,
		string $provider,
		string $model = '',
		array  $raw = [],
	): self {
		return new self(
			content:  '',
			provider: $provider,
			model:    $model,
			success:  false,
			error:    $error,
			raw:      $raw,
		);
	}

	/**
	 * Total number of tokens consumed (input + output).
	 */
	public function totalTokens(): int
	{
		return ($this->usage['input_tokens'] ?? 0) + ($this->usage['output_tokens'] ?? 0);
	}
}
