<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Providers;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\AiResponse;

/**
 * Провајдер за Anthropic Claude (Messages API).
 *
 * Комуницира директно со Anthropic API преку Guzzle.
 * Поддржува multi-turn конверзации и системски пораки.
 */
class ClaudeProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'claude';
    }

    protected function endpoint(): string
    {
        return '/v1/messages';
    }

    protected function buildHeaders(): array
    {
        return [
            'x-api-key'         => $this->config['api_key'],
            'anthropic-version' => $this->config['version'] ?? '2023-06-01',
            'content-type'      => 'application/json',
        ];
    }

    /**
     * Гради payload за Anthropic Messages API.
     *
     * Историјата и тековниот prompt се спојуваат во messages низа.
     * Системската порака се праќа одделно во "system" полето.
     */
    protected function buildPayload(AiRequest $request): array
    {
        $messages = [];

        // Историја на претходни пораки (multi-turn)
        foreach ($request->history as $message) {
            $messages[] = [
                'role'    => $message->role,
                'content' => $message->content,
            ];
        }

        // Тековен prompt како последна корисничка порака
        $messages[] = [
            'role'    => 'user',
            'content' => $request->prompt,
        ];

        $payload = [
            'model'       => $this->model(),
            'messages'    => $messages,
            'max_tokens'  => $request->maxTokens,
            'temperature' => $request->temperature,
        ];

        if ($request->system !== null) {
            $payload['system'] = $request->system;
        }

        return $payload;
    }

    protected function parseResponse(array $data): AiResponse
    {
        $content = $data['content'][0]['text'] ?? '';

        return AiResponse::ok(
            content:    $content,
            provider:   $this->name(),
            model:      $data['model'] ?? $this->model(),
            stopReason: $data['stop_reason'] ?? null,
            usage:      [
                'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            ],
            raw: $data,
        );
    }
}
