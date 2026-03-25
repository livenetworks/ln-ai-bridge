<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Барање кон AI провајдер.
 *
 * Содржи сè што е потребно за еден AI повик: prompt, системска порака,
 * контекст, историја на разговор, и параметри за генерирање.
 */
readonly class AiRequest
{
	/**
	 * @param  string         $prompt      Главен prompt кон AI
	 * @param  string|null    $system      Системска порака (system prompt)
	 * @param  array<string, string> $context Контекст парови (клуч => вредност), се вметнуваат како XML тагови
	 * @param  Message[]      $history     Историја на претходни пораки (multi-turn)
	 * @param  float          $temperature Креативност на одговорот (0.0 - 1.0)
	 * @param  int            $maxTokens   Максимален број токени во одговорот
	 * @param  array<string, mixed> $meta   Дополнителни мета-податоци
	 */
	public function __construct(
		public string  $prompt,
		public ?string $system = null,
		public array   $context = [],
		public array   $history = [],
		public float   $temperature = 0.7,
		public int     $maxTokens = 1024,
		public array   $meta = [],
	) {}
}
