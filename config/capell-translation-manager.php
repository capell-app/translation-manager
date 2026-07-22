<?php

declare(strict_types=1);

return [
    'source_locale' => 'en',

    'locale_pattern' => '/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/',

    'app_source' => [
        'key' => 'app',
        'label' => 'Application',
        'path' => null,
        'writable' => true,
    ],

    'package_paths' => [
        //
    ],

    'vendor_namespaces' => [
        //
    ],

    'package_source_writes' => false,

    'scan_paths' => [
        app_path(),
        resource_path('views'),
        base_path('routes'),
    ],

    'glossary' => [
        //
        // 'fr' => [
        //     'CMS' => 'CMS',
        // ],
    ],

    'ai' => [
        'model' => env('CAPELL_TRANSLATION_MANAGER_AI_MODEL', 'gpt-4o'),
        'max_tokens' => (int) env('CAPELL_TRANSLATION_MANAGER_AI_MAX_TOKENS', 2000),
        'temperature' => (float) env('CAPELL_TRANSLATION_MANAGER_AI_TEMPERATURE', 0.1),
    ],
];
