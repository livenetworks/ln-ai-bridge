<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LiveNetworks\LnAiBridge\DTO\Message;

/**
 * Eloquent model for an individual message in a conversation.
 *
 * Messages are immutable (no updated_at) — once saved, they are never modified.
 */
class AiMessage extends Model
{
	use HasUuids;

	/** Messages are immutable — no updated_at. */
	const UPDATED_AT = null;

	protected $table = 'ai_messages';

	protected $fillable = [
		'conversation_id',
		'role',
		'content',
		'tokens',
		'is_summarized',
		'metadata',
	];

	protected function casts(): array
	{
		return [
			'tokens'         => 'integer',
			'is_summarized'  => 'boolean',
			'metadata'       => 'array',
		];
	}

	/**
	 * The conversation this message belongs to.
	 */
	public function conversation(): BelongsTo
	{
		return $this->belongsTo(AiConversation::class, 'conversation_id');
	}

	/**
	 * Scope: unsummarized messages only.
	 */
	public function scopeUnsummarized(Builder $query): Builder
	{
		return $query->where('is_summarized', false);
	}

	/**
	 * Convert to DTO Message for PromptBuilder compatibility.
	 */
	public function toDto(): Message
	{
		return new Message($this->role, $this->content);
	}
}
