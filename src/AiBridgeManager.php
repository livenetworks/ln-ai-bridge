<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge;

use InvalidArgumentException;
use LiveNetworks\LnAiBridge\Contracts\AiProviderInterface;
use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\Providers\ClaudeProvider;
use LiveNetworks\LnAiBridge\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Log;

/**
 * Главен менаџер (оркестратор) за AI Bridge.
 *
 * Singleton кој управува со провајдерите, го резолвира стандардниот провајдер,
 * и овозможува регистрација на custom провајдери.
 */
class AiBridgeManager
{
    /**
     * Вградени провајдери (driver => class).
     *
     * @var array<string, class-string<AiProviderInterface>>
     */
    private array $drivers = [
        'claude' => ClaudeProvider::class,
        'openai' => OpenAiProvider::class,
    ];

    /**
     * Кеширани инстанци на провајдери.
     *
     * @var array<string, AiProviderInterface>
     */
    private array $resolved = [];

    /**
     * Креира нов PromptBuilder за fluent градење на барања.
     */
    public function prompt(): PromptBuilder
    {
        return new PromptBuilder();
    }

    /**
     * Испраќа барање до AI провајдер.
     *
     * Ако не е наведен провајдер, се користи стандардниот од конфигурацијата.
     * Логирањето е опционално (конфигурирано во ai-bridge.logging).
     */
    public function send(AiRequest $request, ?string $provider = null): AiResponse
    {
        $driver = $provider ?? config('ai-bridge.default', 'claude');
        $instance = $this->resolve($driver);

        $this->logRequest($driver, $request);

        $response = $instance->send($request);

        $this->logResponse($driver, $response);

        return $response;
    }

    /**
     * Регистрира custom провајдер.
     *
     * @param  string $driver  Име на driver-от (пр. "mistral")
     * @param  class-string<AiProviderInterface> $class  Класа на провајдерот
     */
    public function register(string $driver, string $class): self
    {
        $this->drivers[$driver] = $class;

        // Инвалидирај кеширана инстанца ако постои
        unset($this->resolved[$driver]);

        return $this;
    }

    /**
     * Резолвира провајдер инстанца по driver име.
     */
    private function resolve(string $driver): AiProviderInterface
    {
        if (isset($this->resolved[$driver])) {
            return $this->resolved[$driver];
        }

        if (! isset($this->drivers[$driver])) {
            throw new InvalidArgumentException("AI провајдерот [{$driver}] не е регистриран.");
        }

        $config = config("ai-bridge.providers.{$driver}", []);
        $class = $this->drivers[$driver];

        $this->resolved[$driver] = new $class($config);

        return $this->resolved[$driver];
    }

    /**
     * Логирање на барањето (ако е вклучено).
     */
    private function logRequest(string $driver, AiRequest $request): void
    {
        if (! config('ai-bridge.logging', false)) {
            return;
        }

        Log::debug("AiBridge [{$driver}] request", [
            'prompt'      => mb_substr($request->prompt, 0, 200),
            'system'      => $request->system !== null ? mb_substr($request->system, 0, 100) : null,
            'temperature' => $request->temperature,
            'max_tokens'  => $request->maxTokens,
            'history'     => count($request->history),
        ]);
    }

    /**
     * Логирање на одговорот (ако е вклучено).
     */
    private function logResponse(string $driver, AiResponse $response): void
    {
        if (! config('ai-bridge.logging', false)) {
            return;
        }

        Log::debug("AiBridge [{$driver}] response", [
            'success'      => $response->success,
            'model'        => $response->model,
            'total_tokens' => $response->totalTokens(),
            'stop_reason'  => $response->stopReason,
            'error'        => $response->error,
        ]);
    }
}
