<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Services;

use LiveNetworks\LnAiBridge\Contracts\ToolExecutorInterface;
use LiveNetworks\LnAiBridge\DTO\AiResponse;
use LiveNetworks\LnAiBridge\DTO\ToolResult;

/**
 * Orchestrator for executing tool calls.
 *
 * Takes a mapping of tool_name => executor and executes
 * all tool calls from the AI response.
 */
class ToolRunner
{
	/**
	 * @var array<string, ToolExecutorInterface>
	 */
	private array $executors = [];

	/**
	 * @param  array<string, ToolExecutorInterface> $executors Mapping: tool_name => executor
	 */
	public function __construct(array $executors = [])
	{
		$this->executors = $executors;
	}

	/**
	 * Register an executor for a specific tool.
	 */
	public function register(string $toolName, ToolExecutorInterface $executor): void
	{
		$this->executors[$toolName] = $executor;
	}

	/**
	 * Execute all tool calls from the AI response.
	 *
	 * If no executor is registered for a requested tool, returns ToolResult::error().
	 *
	 * @return ToolResult[]
	 */
	public function run(AiResponse $response): array
	{
		$results = [];

		foreach ($response->toolCalls as $call) {
			if (!isset($this->executors[$call->name])) {
				$results[] = ToolResult::error(
					$call->id,
					"Tool [{$call->name}] is not registered.",
				);
				continue;
			}

			try {
				$results[] = $this->executors[$call->name]->execute($call);
			} catch (\Throwable $e) {
				$results[] = ToolResult::error(
					$call->id,
					"Tool [{$call->name}] failed: {$e->getMessage()}",
				);
			}
		}

		return $results;
	}
}
