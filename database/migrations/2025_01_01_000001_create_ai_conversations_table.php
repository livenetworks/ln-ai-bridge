<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Table for AI conversations.
	 */
	public function up(): void
	{
		Schema::create('ai_conversations', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->unsignedBigInteger('tenant_id')->nullable()->index();
			$table->unsignedBigInteger('user_id')->nullable()->index();
			$table->string('context_type', 50)->nullable();
			$table->unsignedBigInteger('context_id')->nullable();
			$table->string('provider', 20);
			$table->string('model', 50);
			$table->text('system_prompt')->nullable();
			$table->string('title', 255)->nullable();
			$table->string('status', 20)->default('active');
			$table->unsignedInteger('message_count')->default(0);
			$table->unsignedInteger('total_tokens')->default(0);
			$table->timestamps();
			$table->softDeletes();

			$table->index(['tenant_id', 'user_id']);
			$table->index(['context_type', 'context_id']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('ai_conversations');
	}
};
