<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge;

use Illuminate\Support\ServiceProvider;

/**
 * Laravel Service Provider за AI Bridge пакетот.
 *
 * Регистрира singleton, ја мерџира конфигурацијата,
 * и овозможува publishable config и SKILL.md.
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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai-bridge.php' => config_path('ai-bridge.php'),
            ], 'ai-bridge-config');

            $this->publishes([
                __DIR__ . '/../SKILL.md' => base_path('.claude/skills/ln-ai-bridge.md'),
            ], 'ai-bridge-skill');
        }
    }
}
