<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Table for tracking consumed tokens (append-only log).
	 */
	public function up(): void
	{
		Schema::create('ai_usage_log', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('tenant_id')->nullable();
			$table->unsignedBigInteger('user_id')->nullable();
			$table->string('provider', 20);
			$table->string('model', 50);
			$table->unsignedInteger('input_tokens');
			$table->unsignedInteger('output_tokens');
			$table->uuid('conversation_id')->nullable();
			$table->timestamp('created_at')->nullable();

			$table->foreign('conversation_id')
				->references('id')
				->on('ai_conversations')
				->nullOnDelete();

			$table->index(['tenant_id', 'created_at']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('ai_usage_log');
	}
};
