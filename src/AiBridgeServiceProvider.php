<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge;

use Illuminate\Support\ServiceProvider;
use LiveNetworks\LnAiBridge\Services\ConversationManager;
use LiveNetworks\LnAiBridge\Services\SummarizationService;
use LiveNetworks\LnAiBridge\Services\UsageTracker;

/**
 * Laravel Service Provider for the AI Bridge package.
 *
 * Registers singletons, merges configuration,
 * and provides publishable config, migrations, and SKILL.md.
 */
class AiBridgeServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/ai-bridge.php',
			'ai-bridge',
		);

		$this->app->singleton(AiBridgeManager::class, function () {
			return new AiBridgeManager();
		});

		$this->app->singleton(UsageTracker::class);
		$this->app->singleton(SummarizationService::class);
		$this->app->singleton(ConversationManager::class);

		// Aliases without Services\ prefix for convenience
		$this->app->alias(ConversationManager::class, 'LiveNetworks\LnAiBridge\ConversationManager');
		$this->app->alias(SummarizationService::class, 'LiveNetworks\LnAiBridge\SummarizationService');
		$this->app->alias(UsageTracker::class, 'LiveNetworks\LnAiBridge\UsageTracker');
	}

	public function boot(): void
	{
		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

		if ($this->app->runningInConsole()) {
			$this->publishes([
				__DIR__ . '/../config/ai-bridge.php' => config_path('ai-bridge.php'),
			], 'ai-bridge-config');

			$this->publishes([
				__DIR__ . '/../database/migrations' => database_path('migrations'),
			], 'ai-bridge-migrations');

			$this->publishes([
				__DIR__ . '/../SKILL.md' => base_path('.claude/skills/ln-ai-bridge.md'),
			], 'ai-bridge-skill');
		}
	}
}
