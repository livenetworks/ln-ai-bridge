<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Table for individual messages in a conversation.
	 */
	public function up(): void
	{
		Schema::create('ai_messages', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->uuid('conversation_id');
			$table->string('role', 20);
			$table->longText('content');
			$table->unsignedInteger('tokens')->nullable();
			$table->boolean('is_summarized')->default(false);
			$table->json('metadata')->nullable();
			$table->timestamp('created_at')->nullable();

			$table->foreign('conversation_id')
				->references('id')
				->on('ai_conversations')
				->cascadeOnDelete();

			$table->index(['conversation_id', 'created_at']);
			$table->index(['conversation_id', 'is_summarized']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('ai_messages');
	}
};
