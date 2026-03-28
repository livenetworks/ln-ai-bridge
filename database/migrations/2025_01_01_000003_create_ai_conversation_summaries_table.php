<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Table for summarized older messages from conversations.
	 */
	public function up(): void
	{
		Schema::create('ai_conversation_summaries', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->uuid('conversation_id');
			$table->text('summary');
			$table->timestamp('messages_from');
			$table->timestamp('messages_until');
			$table->unsignedInteger('messages_count');
			$table->unsignedInteger('tokens_saved');
			$table->timestamp('created_at')->nullable();

			$table->foreign('conversation_id')
				->references('id')
				->on('ai_conversations')
				->cascadeOnDelete();

			$table->index('conversation_id');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('ai_conversation_summaries');
	}
};
