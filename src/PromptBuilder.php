<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge;

use LiveNetworks\LnAiBridge\DTO\AiRequest;
use LiveNetworks\LnAiBridge\DTO\Message;

/**
 * Fluent builder за градење на AI барања.
 *
 * Овозможува чисто и читливо градење на AiRequest објекти.
 * Контекстот се вметнува како XML тагови во prompt-от.
 *
 * Пример:
 *   $request = AiBridge::prompt()
 *       ->system('Ти си корисен асистент.')
 *       ->context('customer_name', 'Јован')
 *       ->prompt('Напиши одговор за клиентот.')
 *       ->temperature(0.5)
 *       ->build();
 */
class PromptBuilder
{
    private string $prompt = '';

    private ?string $system = null;

    /** @var array<string, string> */
    private array $context = [];

    /** @var Message[] */
    private array $history = [];

    private ?float $temperature = null;

    private ?int $maxTokens = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    /**
     * Поставува главен prompt.
     */
    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Поставува системска порака.
     */
    public function system(string $system): self
    {
        $this->system = $system;

        return $this;
    }

    /**
     * Додава контекст пар (клуч => вредност).
     *
     * Контекстот се вметнува како XML тагови во prompt-от при build().
     * Пример: context('customer_name', 'Јован') → <customer_name>Јован</customer_name>
     */
    public function context(string $key, string $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Поставува историја на претходни пораки (multi-turn).
     *
     * @param  Message[] $messages
     */
    public function history(array $messages): self
    {
        $this->history = $messages;

        return $this;
    }

    /**
     * Додава единечна порака во историјата.
     */
    public function addMessage(string $role, string $content): self
    {
        $this->history[] = new Message($role, $content);

        return $this;
    }

    /**
     * Поставува temperature (креативност) на одговорот.
     */
    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Поставува максимален број на токени во одговорот.
     */
    public function maxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Додава мета-податок.
     */
    public function meta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * Гради AiRequest објект.
     *
     * Контекстот се вметнува како XML тагови на почетокот на prompt-от.
     */
    public function build(): AiRequest
    {
        $finalPrompt = $this->buildPromptWithContext();

        return new AiRequest(
            prompt:      $finalPrompt,
            system:      $this->system,
            context:     $this->context,
            history:     $this->history,
            temperature: $this->temperature ?? (float) config('ai-bridge.defaults.temperature', 0.7),
            maxTokens:   $this->maxTokens ?? (int) config('ai-bridge.defaults.max_tokens', 1024),
            meta:        $this->meta,
        );
    }

    /**
     * Го спојува контекстот (XML тагови) со prompt-от.
     *
     * Секој контекст пар се претвора во XML таг:
     * <клуч>вредност</клуч>
     */
    private function buildPromptWithContext(): string
    {
        if (empty($this->context)) {
            return $this->prompt;
        }

        $contextXml = '';
        foreach ($this->context as $key => $value) {
            $contextXml .= "<{$key}>{$value}</{$key}>\n";
        }

        return $contextXml . "\n" . $this->prompt;
    }
}
