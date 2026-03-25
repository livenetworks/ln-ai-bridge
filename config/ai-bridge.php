<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Стандарден провајдер кој се користи кога не е експлицитно наведен.
    |
    */

    'default' => env('AI_BRIDGE_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Конфигурација за секој AI провајдер. Секој провајдер има свој API клуч,
    | модел, и base URL.
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
    | Дали да се логираат AI барањата и одговорите.
    |
    */

    'logging' => env('AI_BRIDGE_LOGGING', false),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Стандардни вредности за temperature, max tokens и timeout.
    |
    */

    'defaults' => [
        'temperature' => 0.4,
        'max_tokens'  => 8192,
        'timeout'     => 60,
    ],

];
