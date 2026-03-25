<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Contracts;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Интерфејс за AI провајдери.
 *
 * Секој провајдер (Claude, OpenAI, итн.) мора да го имплементира овој интерфејс
 * за да може да се користи преку Bridge-от.
 */
interface AiProviderInterface
{
    /**
     * Испраќа барање до AI провајдерот и враќа одговор.
     */
    public function send(AiRequest $request): AiResponse;

    /**
     * Враќа име на провајдерот (пр. "claude", "openai").
     */
    public function name(): string;

    /**
     * Враќа име на моделот кој се користи (пр. "claude-sonnet-4-20250514").
     */
    public function model(): string;
}
