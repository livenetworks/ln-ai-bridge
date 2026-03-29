<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LiveNetworks\LnAiBridge\Contracts\AiProviderInterface;
use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\Services\RetryHandler;

/**
 * Abstract provider with shared HTTP logic.
 *
 * Provides Guzzle communication and error handling.
 * Subclasses only need to implement buildHeaders(), buildPayload() and parseResponse().
 */
abstract class AbstractProvider implements AiProviderInterface
{
	protected Client $client;

	/**
	 * @param  array<string, mixed> $config Provider configuration (api_key, model, base_url, etc.)
	 */
	public function __construct(
		protected array $config,
	) {
		$this->client = new Client([
			'base_uri' => $this->config['base_url'] ?? '',
			'timeout'  => config('ai-bridge.defaults.timeout', 30),
		]);
	}

	/**
	 * Send a request to the AI API.
	 *
	 * Uses buildHeaders() and buildPayload() from the subclass to compose
	 * the HTTP request, sends it, and parses the response.
	 * Automatically retries on retryable failures (429, 5xx) with exponential backoff.
	 */
	public function send(AiRequest $request): AiResponse
	{
		$retryHandler = new RetryHandler(config('ai-bridge.retry', []));

		try {
			return $retryHandler->execute(function () use ($request) {
				$response = $this->client->post($this->endpoint(), [
					'headers' => $this->buildHeaders(),
					'json'    => $this->buildPayload($request),
				]);

				$data = json_decode($response->getBody()->getContents(), true);

				return $this->parseResponse($data);
			}, $this->name());
		} catch (GuzzleException $e) {
			return AiResponse::fail(
				error:    $e->getMessage(),
				provider: $this->name(),
				model:    $this->model(),
			);
		}
	}

	public function model(): string
	{
		return $this->config['model'] ?? '';
	}

	/**
	 * API endpoint path (e.g. "/v1/messages").
	 */
	abstract protected function endpoint(): string;

	/**
	 * HTTP headers for the API request.
	 *
	 * @return array<string, string>
	 */
	abstract protected function buildHeaders(): array;

	/**
	 * HTTP request body for the specific provider.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function buildPayload(AiRequest $request): array;

	/**
	 * Parse the raw API response into a unified AiResponse.
	 *
	 * @param  array<string, mixed> $data
	 */
	abstract protected function parseResponse(array $data): AiResponse;
}
