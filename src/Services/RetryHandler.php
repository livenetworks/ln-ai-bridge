<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Services;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Generic retry handler with exponential backoff.
 *
 * Retries failed HTTP operations when the status code is retryable
 * (e.g. 429 rate limit, 5xx server errors). Honors Retry-After headers.
 * Provider-agnostic — knows nothing about AI specifics.
 */
class RetryHandler
{
	private bool $enabled;
	private int $maxRetries;
	private int $baseDelayMs;
	private int $multiplier;

	/** @var int[] */
	private array $retryableCodes;

	/**
	 * @param array<string, mixed> $config Retry configuration from ai-bridge.retry
	 */
	public function __construct(array $config = [])
	{
		$this->enabled        = (bool) ($config['enabled'] ?? true);
		$this->maxRetries     = (int) ($config['max_retries'] ?? 3);
		$this->baseDelayMs    = (int) ($config['base_delay_ms'] ?? 1000);
		$this->multiplier     = (int) ($config['multiplier'] ?? 2);
		$this->retryableCodes = (array) ($config['retryable_codes'] ?? [429, 500, 502, 503, 529]);
	}

	/**
	 * Execute a callable with automatic retry on retryable failures.
	 *
	 * If retry is disabled, the callable is invoked once.
	 * On retryable GuzzleException, retries up to max_retries with exponential backoff.
	 * Honors Retry-After header for 429 responses.
	 * Non-retryable status codes are thrown immediately.
	 *
	 * @param callable $operation The HTTP operation to execute
	 * @param string   $context   Context label for log messages (e.g. provider name)
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException When all retries are exhausted or error is non-retryable
	 */
	public function execute(callable $operation, string $context = ''): mixed
	{
		if (!$this->enabled) {
			return $operation();
		}

		$lastException = null;

		for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
			try {
				return $operation();
			} catch (RequestException $e) {
				$statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;

				if (!$this->isRetryable($statusCode) || $attempt === $this->maxRetries) {
					throw $e;
				}

				$delayMs = $this->calculateDelay($attempt, $e);

				Log::warning("RetryHandler [{$context}] retry", [
					'attempt'     => $attempt + 1,
					'max_retries' => $this->maxRetries,
					'status_code' => $statusCode,
					'delay_ms'    => $delayMs,
				]);

				$this->sleep($delayMs);

				$lastException = $e;
			}
		}

		throw $lastException;
	}

	/**
	 * Check if a status code is retryable.
	 */
	private function isRetryable(int $statusCode): bool
	{
		return in_array($statusCode, $this->retryableCodes, true);
	}

	/**
	 * Calculate delay in milliseconds for the given attempt.
	 *
	 * Uses Retry-After header if present on 429 responses,
	 * otherwise falls back to exponential backoff.
	 */
	private function calculateDelay(int $attempt, RequestException $e): int
	{
		if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 429) {
			$retryAfter = $e->getResponse()->getHeaderLine('Retry-After');

			if ($retryAfter !== '') {
				$seconds = is_numeric($retryAfter)
					? (int) $retryAfter
					: max(0, strtotime($retryAfter) - time());

				if ($seconds > 0) {
					return $seconds * 1000;
				}
			}
		}

		return (int) ($this->baseDelayMs * ($this->multiplier ** $attempt));
	}

	/**
	 * Sleep for the given number of milliseconds.
	 *
	 * Extracted for testability.
	 */
	protected function sleep(int $milliseconds): void
	{
		usleep($milliseconds * 1000);
	}
}
