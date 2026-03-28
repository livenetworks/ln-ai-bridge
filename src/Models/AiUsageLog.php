<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for append-only token usage log.
 *
 * Used for billing/tracking. Auto-increment PK (not UUID)
 * since it's an append-only log with no need for distributed IDs.
 */
class AiUsageLog extends Model
{
	/** Log is append-only — no updated_at. */
	const UPDATED_AT = null;

	protected $table = 'ai_usage_log';

	protected $fillable = [
		'tenant_id',
		'user_id',
		'provider',
		'model',
		'input_tokens',
		'output_tokens',
		'conversation_id',
	];

	protected function casts(): array
	{
		return [
			'tenant_id'     => 'integer',
			'user_id'       => 'integer',
			'input_tokens'  => 'integer',
			'output_tokens' => 'integer',
		];
	}

	/**
	 * The conversation (nullable — can be a standalone call).
	 */
	public function conversation(): BelongsTo
	{
		return $this->belongsTo(AiConversation::class, 'conversation_id');
	}

	/**
	 * Total tokens consumed (input + output).
	 */
	public function totalTokens(): int
	{
		return $this->input_tokens + $this->output_tokens;
	}
}
