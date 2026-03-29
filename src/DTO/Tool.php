<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\DTO;

/**
 * Tool definition that the AI can invoke.
 *
 * Each tool has a name, description, and JSON Schema for its parameters.
 * Provider-specific format is generated via toClaudeFormat() / toOpenAiFormat().
 */
readonly class Tool
{
	/**
	 * @param  string               $name        Tool name (e.g. "get_document_content")
	 * @param  string               $description Description for the AI (e.g. "Retrieves the full content of a document by ID")
	 * @param  array<string, mixed> $parameters  JSON Schema of input parameters
	 */
	public function __construct(
		public string $name,
		public string $description,
		public array  $parameters,
	) {}

	/**
	 * Factory method for readable creation.
	 *
	 * @param  array<string, mixed> $parameters JSON Schema of parameters
	 */
	public static function make(string $name, string $description, array $parameters): self
	{
		return new self($name, $description, $parameters);
	}

	/**
	 * Format for the Anthropic Claude API.
	 *
	 * @return array<string, mixed>
	 */
	public function toClaudeFormat(): array
	{
		return [
			'name'         => $this->name,
			'description'  => $this->description,
			'input_schema' => [
				'type'       => 'object',
				'properties' => $this->parameters['properties'] ?? [],
				'required'   => $this->parameters['required'] ?? [],
			],
		];
	}

	/**
	 * Format for the OpenAI API.
	 *
	 * @return array<string, mixed>
	 */
	public function toOpenAiFormat(): array
	{
		return [
			'type'     => 'function',
			'function' => [
				'name'        => $this->name,
				'description' => $this->description,
				'parameters'  => [
					'type'       => 'object',
					'properties' => $this->parameters['properties'] ?? [],
					'required'   => $this->parameters['required'] ?? [],
				],
			],
		];
	}
}
