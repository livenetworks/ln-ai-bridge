<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge;

use InvalidArgumentException;
use LiveNetworks\LnAiBridge\Contracts\AiProviderInterface;
use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\Providers\ClaudeProvider;
use LiveNetworks\LnAiBridge\Providers\OpenAiProvider;
use LiveNetworks\LnAiBridge\Services\UsageTracker;
use Illuminate\Support\Facades\Log;

/**
 * Main manager (orchestrator) for AI Bridge.
 *
 * Singleton that manages providers, resolves the default provider,
 * and allows registration of custom providers.
 */
class AiBridgeManager
{
	/**
	 * Built-in providers (driver => class).
	 *
	 * @var array<string, class-string<AiProviderInterface>>
	 */
	private array $drivers = [
		'claude' => ClaudeProvider::class,
		'openai' => OpenAiProvider::class,
	];

	/**
	 * Cached provider instances.
	 *
	 * @var array<string, AiProviderInterface>
	 */
	private array $resolved = [];

	/**
	 * Create a new PromptBuilder for fluent request construction.
	 */
	public function prompt(string $prompt = ''): PromptBuilder
	{
		$builder = new PromptBuilder();

		if ($prompt !== '') {
			$builder->prompt($prompt);
		}

		return $builder;
	}

	/**
	 * Send a request to an AI provider.
	 *
	 * If no provider is specified, the default from configuration is used.
	 * Logging is optional (configured in ai-bridge.logging).
	 */
	public function send(AiRequest $request, ?string $provider = null): AiResponse
	{
		$driver = $provider ?? config('ai-bridge.default', 'claude');
		$instance = $this->resolve($driver);

		$this->logRequest($driver, $request);

		$response = $instance->send($request);

		$this->logResponse($driver, $response);

		if ($response->success) {
			$this->trackUsage($response, $request);
		}

		return $response;
	}

	/**
	 * Register a custom provider.
	 *
	 * @param  string $driver  Driver name (e.g. "mistral")
	 * @param  class-string<AiProviderInterface> $class  Provider class
	 */
	public function register(string $driver, string $class): self
	{
		$this->drivers[$driver] = $class;

		// Invalidate cached instance if it exists
		unset($this->resolved[$driver]);

		return $this;
	}

	/**
	 * Resolve a provider instance by driver name.
	 */
	private function resolve(string $driver): AiProviderInterface
	{
		if (isset($this->resolved[$driver])) {
			return $this->resolved[$driver];
		}

		if (! isset($this->drivers[$driver])) {
			throw new InvalidArgumentException("AI provider [{$driver}] is not registered.");
		}

		$config = config("ai-bridge.providers.{$driver}", []);
		$class = $this->drivers[$driver];

		$this->resolved[$driver] = new $class($config);

		return $this->resolved[$driver];
	}

	/**
	 * Log the request (if logging is enabled).
	 */
	private function logRequest(string $driver, AiRequest $request): void
	{
		if (! config('ai-bridge.logging', false)) {
			return;
		}

		Log::debug("AiBridge [{$driver}] request", [
			'prompt'      => mb_substr($request->prompt, 0, 200),
			'system'      => $request->system !== null ? mb_substr($request->system, 0, 100) : null,
			'temperature' => $request->temperature,
			'max_tokens'  => $request->maxTokens,
			'history'     => count($request->history),
		]);
	}

	/**
	 * Log the response (if logging is enabled).
	 */
	private function logResponse(string $driver, AiResponse $response): void
	{
		if (! config('ai-bridge.logging', false)) {
			return;
		}

		Log::debug("AiBridge [{$driver}] response", [
			'success'      => $response->success,
			'model'        => $response->model,
			'total_tokens' => $response->totalTokens(),
			'stop_reason'  => $response->stopReason,
			'error'        => $response->error,
		]);
	}

	/**
	 * Automatic usage tracking after every successful send().
	 *
	 * ConversationManager sets meta['skip_auto_tracking'] = true
	 * to avoid double-logging (it logs explicitly).
	 */
	private function trackUsage(AiResponse $response, AiRequest $request): void
	{
		if (! config('ai-bridge.usage.tracking_enabled', true)) {
			return;
		}

		if (! empty($request->meta['skip_auto_tracking'])) {
			return;
		}

		try {
			/** @var UsageTracker $tracker */
			$tracker = app(UsageTracker::class);
			$tracker->log(
				response: $response,
				tenantId: isset($request->meta['tenant_id']) ? (int) $request->meta['tenant_id'] : null,
				userId: isset($request->meta['user_id']) ? (int) $request->meta['user_id'] : null,
				conversationId: $request->meta['conversation_id'] ?? null,
			);
		} catch (\Throwable) {
			// Never let a tracking error break the main flow
		}
	}
}
