<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Facades;

use Illuminate\Support\Facades\Facade;
use LiveNetworks\LnAiBridge\AiBridgeManager;
use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\PromptBuilder;

/**
 * Facade за AiBridgeManager.
 *
 * @method static PromptBuilder prompt()
 * @method static AiResponse send(AiRequest $request, ?string $provider = null)
 * @method static AiBridgeManager register(string $driver, string $class)
 *
 * @see AiBridgeManager
 */
class AiBridge extends Facade
{
	protected static function getFacadeAccessor(): string
	{
		return AiBridgeManager::class;
	}
}
