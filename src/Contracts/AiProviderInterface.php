<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Contracts;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Interface for AI providers.
 *
 * Every provider (Claude, OpenAI, etc.) must implement this interface
 * to be usable through the Bridge.
 */
interface AiProviderInterface
{
	/**
	 * Send a request to the AI provider and return a response.
	 */
	public function send(AiRequest $request): AiResponse;

	/**
	 * Return the provider name (e.g. "claude", "openai").
	 */
	public function name(): string;

	/**
	 * Return the model name in use (e.g. "claude-sonnet-4-20250514").
	 */
	public function model(): string;
}
