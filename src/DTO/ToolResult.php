<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Result from an executed tool call.
 *
 * Returned back to the AI so it can continue generating a response.
 */
readonly class ToolResult
{
	/**
	 * @param  string $toolCallId ID of the call we are responding to
	 * @param  string $content    The result (text or JSON string)
	 * @param  bool   $isError    Whether the tool threw an error
	 */
	public function __construct(
		public string $toolCallId,
		public string $content,
		public bool   $isError = false,
	) {}

	/**
	 * Create a successful tool result.
	 */
	public static function success(string $toolCallId, string $content): self
	{
		return new self($toolCallId, $content, false);
	}

	/**
	 * Create an error tool result.
	 */
	public static function error(string $toolCallId, string $message): self
	{
		return new self($toolCallId, $message, true);
	}
}
