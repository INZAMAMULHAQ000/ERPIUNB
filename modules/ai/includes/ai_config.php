<?php
/**
 * AI Module Configuration.
 *
 * Central configuration for all AI module capabilities.
 * This configuration does not yet control business functionality.
 * It is only the foundation for future AI features.
 */

return [
    'module' => 'ai',

    'version' => '1.0.0',

    'capabilities' => [
        'assistant'  => true,
        'providers'  => true,
        'models'     => true,
        'agents'     => true,
        'knowledge'  => true,
        'tools'      => true,
        'automation' => true,
        'monitoring' => true,
        'settings'   => true,
    ],

    'icons' => [
        'assistant'  => 'fas fa-robot',
        'providers'  => 'fas fa-server',
        'models'     => 'fas fa-brain',
        'agents'     => 'fas fa-user-secret',
        'knowledge'  => 'fas fa-book-open',
        'tools'      => 'fas fa-screwdriver-wrench',
        'automation' => 'fas fa-robot',
        'monitoring' => 'fas fa-chart-line',
        'settings'   => 'fas fa-cog',
    ],

    'sections' => [
        'management' => [
            'label' => 'Management Center',
            'icon'  => 'fas fa-cogs',
            'items' => [
                'providers' => [
                    'label' => 'Providers',
                    'icon'  => 'fas fa-server',
                    'description' => 'Manage AI service providers and connections.',
                ],
                'models' => [
                    'label' => 'Models',
                    'icon'  => 'fas fa-brain',
                    'description' => 'Manage available AI models and configurations.',
                ],
                'agents' => [
                    'label' => 'Agents',
                    'icon'  => 'fas fa-user-secret',
                    'description' => 'Configure specialized AI agents.',
                ],
                'knowledge' => [
                    'label' => 'Knowledge',
                    'icon'  => 'fas fa-book-open',
                    'description' => 'Manage company knowledge and context sources.',
                ],
                'tools' => [
                    'label' => 'Tools',
                    'icon'  => 'fas fa-screwdriver-wrench',
                    'description' => 'Manage ERP capabilities exposed to AI.',
                ],
            ],
        ],
    ],
];
