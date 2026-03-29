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
	 * @param  Tool[]         $tools       Tool definitions the AI can invoke
	 * @param  ToolResult[]   $toolResults Results from previous tool executions
	 */
	public function __construct(
		public string  $prompt,
		public ?string $system = null,
		public array   $context = [],
		public array   $history = [],
		public float   $temperature = 0.7,
		public int     $maxTokens = 1024,
		public array   $meta = [],
		public array   $tools = [],
		public array   $toolResults = [],
	) {}

	/**
	 * Create a new request with overridden parameters (immutable copy).
	 *
	 * @param  array<string, mixed> $overrides Parameters to override
	 */
	public function with(array $overrides): self
	{
		return new self(
			prompt:      $overrides['prompt'] ?? $this->prompt,
			system:      array_key_exists('system', $overrides) ? $overrides['system'] : $this->system,
			context:     $overrides['context'] ?? $this->context,
			history:     $overrides['history'] ?? $this->history,
			temperature: $overrides['temperature'] ?? $this->temperature,
			maxTokens:   $overrides['maxTokens'] ?? $this->maxTokens,
			meta:        $overrides['meta'] ?? $this->meta,
			tools:       $overrides['tools'] ?? $this->tools,
			toolResults: $overrides['toolResults'] ?? $this->toolResults,
		);
	}
}
