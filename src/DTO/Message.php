<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Message in a conversation with an AI provider.
 *
 * Represents a single message with a role and content.
 * Used for building conversation history (multi-turn).
 */
readonly class Message
{
	public function __construct(
		public string $role,
		public string $content,
	) {}

	/**
	 * Create a user message.
	 */
	public static function user(string $content): self
	{
		return new self('user', $content);
	}

	/**
	 * Create an assistant message.
	 */
	public static function assistant(string $content): self
	{
		return new self('assistant', $content);
	}
}
