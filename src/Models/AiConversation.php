<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent model for AI conversations.
 *
 * Represents a single conversation with an AI provider, including
 * message history, summaries, and usage tracking.
 * tenant_id is ONLY for billing/tracking — the bridge does NOT filter by it.
 */
class AiConversation extends Model
{
	use HasUuids;
	use SoftDeletes;

	protected $table = 'ai_conversations';

	protected $fillable = [
		'tenant_id',
		'user_id',
		'context_type',
		'context_id',
		'provider',
		'model',
		'system_prompt',
		'title',
		'status',
	];

	protected function casts(): array
	{
		return [
			'tenant_id'     => 'integer',
			'user_id'       => 'integer',
			'context_id'    => 'integer',
			'message_count' => 'integer',
			'total_tokens'  => 'integer',
		];
	}

	/**
	 * Messages in this conversation.
	 */
	public function messages(): HasMany
	{
		return $this->hasMany(AiMessage::class, 'conversation_id');
	}

	/**
	 * Summaries of older messages.
	 */
	public function summaries(): HasMany
	{
		return $this->hasMany(AiConversationSummary::class, 'conversation_id');
	}

	/**
	 * Token usage logs.
	 */
	public function usageLogs(): HasMany
	{
		return $this->hasMany(AiUsageLog::class, 'conversation_id');
	}

	/**
	 * Scope: active conversations only.
	 */
	public function scopeActive(Builder $query): Builder
	{
		return $query->where('status', 'active');
	}

	/**
	 * Find or create an active conversation for a given context.
	 *
	 * @param  array<string, mixed> $attributes Additional attributes for creation
	 */
	public static function forContext(string $type, int|string $id, array $attributes = []): static
	{
		return static::firstOrCreate(
			[
				'context_type' => $type,
				'context_id'   => $id,
				'status'       => 'active',
			],
			$attributes,
		);
	}
}
