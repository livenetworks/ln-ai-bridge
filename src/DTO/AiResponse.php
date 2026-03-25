<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Одговор од AI провајдер.
 *
 * Унифициран формат за одговори од сите провајдери.
 * Содржи содржина, мета-информации, и суров одговор за дебагирање.
 */
readonly class AiResponse
{
    /**
     * @param  string      $content    Текстуална содржина на одговорот
     * @param  string      $provider   Име на провајдерот (claude, openai)
     * @param  string      $model      Модел кој го генерирал одговорот
     * @param  bool        $success    Дали барањето е успешно
     * @param  string|null $error      Порака за грешка (ако има)
     * @param  string|null $stopReason Причина за запирање на генерирањето
     * @param  array<string, int> $usage Потрошувачка на токени (input_tokens, output_tokens)
     * @param  array<string, mixed> $raw Суров одговор од API-то
     */
    public function __construct(
        public string  $content,
        public string  $provider,
        public string  $model,
        public bool    $success,
        public ?string $error = null,
        public ?string $stopReason = null,
        public array   $usage = [],
        public array   $raw = [],
    ) {}

    /**
     * Креира успешен одговор.
     *
     * @param  array<string, int>   $usage
     * @param  array<string, mixed> $raw
     */
    public static function ok(
        string  $content,
        string  $provider,
        string  $model,
        ?string $stopReason = null,
        array   $usage = [],
        array   $raw = [],
    ): self {
        return new self(
            content:    $content,
            provider:   $provider,
            model:      $model,
            success:    true,
            stopReason: $stopReason,
            usage:      $usage,
            raw:        $raw,
        );
    }

    /**
     * Креира неуспешен одговор (грешка).
     *
     * @param  array<string, mixed> $raw
     */
    public static function fail(
        string $error,
        string $provider,
        string $model = '',
        array  $raw = [],
    ): self {
        return new self(
            content:  '',
            provider: $provider,
            model:    $model,
            success:  false,
            error:    $error,
            raw:      $raw,
        );
    }

    /**
     * Вкупен број на потрошени токени (input + output).
     */
    public function totalTokens(): int
    {
        return ($this->usage['input_tokens'] ?? 0) + ($this->usage['output_tokens'] ?? 0);
    }
}
