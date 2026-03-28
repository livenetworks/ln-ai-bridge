<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Services;

use Carbon\Carbon;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\Models\AiUsageLog;

/**
 * Service for tracking token usage.
 *
 * Logs every AI call to ai_usage_log and provides
 * aggregated reports by tenant and user for billing.
 */
class UsageTracker
{
	/**
	 * Log token usage from an AI response.
	 */
	public function log(
		AiResponse $response,
		?int $tenantId = null,
		?int $userId = null,
		?string $conversationId = null,
	): ?AiUsageLog {
		if (! config('ai-bridge.usage.tracking_enabled', true)) {
			return null;
		}

		return AiUsageLog::create([
			'tenant_id'       => $tenantId,
			'user_id'         => $userId,
			'provider'        => $response->provider,
			'model'           => $response->model,
			'input_tokens'    => $response->usage['input_tokens'] ?? 0,
			'output_tokens'   => $response->usage['output_tokens'] ?? 0,
			'conversation_id' => $conversationId,
		]);
	}

	/**
	 * Total usage by tenant for a given period.
	 *
	 * @return array{input_tokens: int, output_tokens: int, total_tokens: int, request_count: int}
	 */
	public function getTenantUsage(int $tenantId, ?Carbon $from = null, ?Carbon $to = null): array
	{
		return $this->aggregate(
			AiUsageLog::where('tenant_id', $tenantId),
			$from,
			$to,
		);
	}

	/**
	 * Total usage by user for a given period.
	 *
	 * @return array{input_tokens: int, output_tokens: int, total_tokens: int, request_count: int}
	 */
	public function getUserUsage(int $userId, ?Carbon $from = null, ?Carbon $to = null): array
	{
		return $this->aggregate(
			AiUsageLog::where('user_id', $userId),
			$from,
			$to,
		);
	}

	/**
	 * Aggregate usage from query builder.
	 *
	 * @return array{input_tokens: int, output_tokens: int, total_tokens: int, request_count: int}
	 */
	private function aggregate($query, ?Carbon $from, ?Carbon $to): array
	{
		if ($from !== null) {
			$query->where('created_at', '>=', $from);
		}

		if ($to !== null) {
			$query->where('created_at', '<=', $to);
		}

		$result = $query->selectRaw('
			COALESCE(SUM(input_tokens), 0) as input_tokens,
			COALESCE(SUM(output_tokens), 0) as output_tokens,
			COALESCE(SUM(input_tokens + output_tokens), 0) as total_tokens,
			COUNT(*) as request_count
		')->first();

		return [
			'input_tokens'  => (int) $result->input_tokens,
			'output_tokens' => (int) $result->output_tokens,
			'total_tokens'  => (int) $result->total_tokens,
			'request_count' => (int) $result->request_count,
		];
	}
}
