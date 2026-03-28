<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\Message;

/**
 * Fluent builder for constructing AI requests.
 *
 * Enables clean and readable construction of AiRequest objects.
 * Context is injected as XML tags into the prompt.
 *
 * Example:
 *   $request = AiBridge::prompt()
 *       ->system('You are a helpful assistant.')
 *       ->context('customer_name', 'John')
 *       ->prompt('Write a response for the customer.')
 *       ->temperature(0.5)
 *       ->build();
 */
class PromptBuilder
{
	private string $prompt = '';

	private ?string $system = null;

	/** @var array<string, string> */
	private array $context = [];

	/** @var Message[] */
	private array $history = [];

	private ?float $temperature = null;

	private ?int $maxTokens = null;

	/** @var array<string, mixed> */
	private array $meta = [];

	/**
	 * Set the main prompt.
	 */
	public function prompt(string $prompt): self
	{
		$this->prompt = $prompt;

		return $this;
	}

	/**
	 * Set the system message.
	 */
	public function system(string $system): self
	{
		$this->system = $system;

		return $this;
	}

	/**
	 * Add a context pair (key => value).
	 *
	 * Context is injected as XML tags into the prompt during build().
	 * Example: context('customer_name', 'John') -> <customer_name>John</customer_name>
	 */
	public function context(string $key, string $value): self
	{
		$this->context[$key] = $value;

		return $this;
	}

	/**
	 * Set the previous message history (multi-turn).
	 *
	 * @param  Message[] $messages
	 */
	public function history(array $messages): self
	{
		$this->history = $messages;

		return $this;
	}

	/**
	 * Append a single message to the history.
	 */
	public function addMessage(string $role, string $content): self
	{
		$this->history[] = new Message($role, $content);

		return $this;
	}

	/**
	 * Set the response temperature (creativity).
	 */
	public function temperature(float $temperature): self
	{
		$this->temperature = $temperature;

		return $this;
	}

	/**
	 * Set the maximum number of tokens in the response.
	 */
	public function maxTokens(int $maxTokens): self
	{
		$this->maxTokens = $maxTokens;

		return $this;
	}

	/**
	 * Add a metadata entry.
	 */
	public function meta(string $key, mixed $value): self
	{
		$this->meta[$key] = $value;

		return $this;
	}

	/**
	 * Build the AiRequest object.
	 *
	 * Context is injected as XML tags at the beginning of the prompt.
	 */
	public function build(): AiRequest
	{
		$finalPrompt = $this->buildPromptWithContext();

		return new AiRequest(
			prompt:      $finalPrompt,
			system:      $this->system,
			context:     $this->context,
			history:     $this->history,
			temperature: $this->temperature ?? (float) config('ai-bridge.defaults.temperature', 0.7),
			maxTokens:   $this->maxTokens ?? (int) config('ai-bridge.defaults.max_tokens', 1024),
			meta:        $this->meta,
		);
	}

	/**
	 * Merge context (XML tags) with the prompt.
	 *
	 * Each context pair is converted to an XML tag:
	 * <key>value</key>
	 */
	private function buildPromptWithContext(): string
	{
		if (empty($this->context)) {
			return $this->prompt;
		}

		$contextXml = '';
		foreach ($this->context as $key => $value) {
			$contextXml .= "<{$key}>{$value}</{$key}>\n";
		}

		return $contextXml . "\n" . $this->prompt;
	}
}
