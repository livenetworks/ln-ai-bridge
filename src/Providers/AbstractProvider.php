<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LiveNetworks\LnAiBridge\Contracts\AiProviderInterface;
use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Апстрактен провајдер со заедничка HTTP логика.
 *
 * Ја обезбедува Guzzle комуникацијата и error handling-от.
 * Субкласите треба само да ги имплементираат buildHeaders(), buildPayload() и parseResponse().
 */
abstract class AbstractProvider implements AiProviderInterface
{
    protected Client $client;

    /**
     * @param  array<string, mixed> $config Конфигурација на провајдерот (api_key, model, base_url, итн.)
     */
    public function __construct(
        protected array $config,
    ) {
        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? '',
            'timeout'  => config('ai-bridge.defaults.timeout', 30),
        ]);
    }

    /**
     * Испраќа барање до AI API-то.
     *
     * Ги користи buildHeaders() и buildPayload() од субкласата за да го
     * состави HTTP барањето, го испраќа, и го парсира одговорот.
     */
    public function send(AiRequest $request): AiResponse
    {
        try {
            $response = $this->client->post($this->endpoint(), [
                'headers' => $this->buildHeaders(),
                'json'    => $this->buildPayload($request),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->parseResponse($data);
        } catch (GuzzleException $e) {
            return AiResponse::fail(
                error:    $e->getMessage(),
                provider: $this->name(),
                model:    $this->model(),
            );
        }
    }

    public function model(): string
    {
        return $this->config['model'] ?? '';
    }

    /**
     * API endpoint патека (пр. "/v1/messages").
     */
    abstract protected function endpoint(): string;

    /**
     * HTTP хедери за API барањето.
     *
     * @return array<string, string>
     */
    abstract protected function buildHeaders(): array;

    /**
     * Тело на HTTP барањето за конкретниот провајдер.
     *
     * @return array<string, mixed>
     */
    abstract protected function buildPayload(AiRequest $request): array;

    /**
     * Парсирање на суровиот API одговор во унифициран AiResponse.
     *
     * @param  array<string, mixed> $data
     */
    abstract protected function parseResponse(array $data): AiResponse;
}
