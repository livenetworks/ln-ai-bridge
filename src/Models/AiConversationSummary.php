<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for summarization of older messages.
 *
 * Stores summarized text from a group of messages to save tokens
 * when sending history to the AI provider.
 */
class AiConversationSummary extends Model
{
	use HasUuids;

	/** Summaries are immutable — no updated_at. */
	const UPDATED_AT = null;

	protected $table = 'ai_conversation_summaries';

	protected $fillable = [
		'conversation_id',
		'summary',
		'messages_from',
		'messages_until',
		'messages_count',
		'tokens_saved',
	];

	protected function casts(): array
	{
		return [
			'messages_from'  => 'datetime',
			'messages_until' => 'datetime',
			'messages_count' => 'integer',
			'tokens_saved'   => 'integer',
		];
	}

	/**
	 * The conversation this summary belongs to.
	 */
	public function conversation(): BelongsTo
	{
		return $this->belongsTo(AiConversation::class, 'conversation_id');
	}
}
