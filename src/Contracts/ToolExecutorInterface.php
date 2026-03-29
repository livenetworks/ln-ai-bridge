<?php

declare(strict_types=1);

namespace LiveNetworks\LnAiBridge\Contracts;

use LiveNetworks\LnAiBridge\DTO\ToolCall;
use LiveNetworks\LnAiBridge\DTO\ToolResult;

/**
 * Interface for executing tool calls.
 *
 * The consuming application implements this for each tool the AI can invoke.
 * The bridge calls execute() automatically when the AI requests a tool.
 */
interface ToolExecutorInterface
{
	/**
	 * Execute a tool call and return the result.
	 *
	 * @param  ToolCall $call The tool call requested by the AI
	 * @return ToolResult     Result (success or error)
	 */
	public function execute(ToolCall $call): ToolResult;
}
