<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Request to an AI provider.
 *
 * Contains everything needed for a single AI call: prompt, system message,
 * context, conversation history, and generation parameters.
 */
readonly class AiRequest
{
	/**
	 * @param  string         $prompt      Main prompt to AI
	 * @param  string|null    $system      System message (system prompt)
	 * @param  array<string, string> $context Context key-value pairs, injected as XML tags
	 * @param  Message[]      $history     Previous messages history (multi-turn)
	 * @param  float          $temperature Response creativity (0.0 - 1.0)
	 * @param  int            $maxTokens   Maximum tokens in the response
	 * @param  array<string, mixed> $meta   Additional metadata
	 */
	public function __construct(
		public string  $prompt,
		public ?string $system = null,
		public array   $context = [],
		public array   $history = [],
		public float   $temperature = 0.7,
		public int     $maxTokens = 1024,
		public array   $meta = [],
	) {}
}
