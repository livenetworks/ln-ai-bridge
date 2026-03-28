<?php

declare(strict_types=1);

return [

	/*
	|--------------------------------------------------------------------------
	| Default AI Provider
	|--------------------------------------------------------------------------
	|
	| Default provider used when none is explicitly specified.
	|
	 */

	'default' => env('AI_BRIDGE_PROVIDER', 'claude'),

	/*
	|--------------------------------------------------------------------------
	| AI Providers
	|--------------------------------------------------------------------------
	|
	| Configuration for each AI provider. Each provider has its own API key,
	| model, and base URL.
	|
	 */

	'providers' => [

		'claude' => [
			'api_key'  => env('AI_BRIDGE_CLAUDE_API_KEY'),
			'model'    => env('AI_BRIDGE_CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
			'base_url' => env('AI_BRIDGE_CLAUDE_BASE_URL', 'https://api.anthropic.com'),
			'version'  => env('AI_BRIDGE_CLAUDE_VERSION', '2023-06-01'),
		],

		'openai' => [
			'api_key'  => env('AI_BRIDGE_OPENAI_API_KEY'),
			'model'    => env('AI_BRIDGE_OPENAI_MODEL', 'gpt-4o'),
			'base_url' => env('AI_BRIDGE_OPENAI_BASE_URL', 'https://api.openai.com'),
		],

	],

	/*
	|--------------------------------------------------------------------------
	| Logging
	|--------------------------------------------------------------------------
	|
	| Whether to log AI requests and responses.
	|
	 */

	'logging' => env('AI_BRIDGE_LOGGING', false),

	/*
	|--------------------------------------------------------------------------
	| Defaults
	|--------------------------------------------------------------------------
	|
	| Default values for temperature, max tokens, and timeout.
	|
	 */

	'defaults' => [
		'temperature' => 0.4,
		'max_tokens'  => 8192,
		'timeout'     => 60,
	],

	/*
	|--------------------------------------------------------------------------
	| Conversation
	|--------------------------------------------------------------------------
	|
	| Settings for conversation management and automatic summarization.
	|
	 */

	'conversation' => [
		'summarize_threshold' => 20,
		'keep_recent'         => 6,
		'summary_max_tokens'  => 500,
	],

	/*
	|--------------------------------------------------------------------------
	| Usage Tracking
	|--------------------------------------------------------------------------
	|
	| Token usage tracking for billing/tracking.
	|
	 */

	'usage' => [
		'tracking_enabled' => env('AI_BRIDGE_USAGE_TRACKING', true),
	],

];
