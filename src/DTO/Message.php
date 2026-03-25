<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Порака во конверзација со AI провајдер.
 *
 * Претставува единечна порака со улога (role) и содржина (content).
 * Се користи за градење на историја на разговор (multi-turn).
 */
readonly class Message
{
	public function __construct(
		public string $role,
		public string $content,
	) {}

	/**
	 * Креира корисничка порака.
	 */
	public static function user(string $content): self
	{
		return new self('user', $content);
	}

	/**
	 * Креира асистентска порака.
	 */
	public static function assistant(string $content): self
	{
		return new self('assistant', $content);
	}
}
