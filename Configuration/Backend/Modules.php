<?php

declare(strict_types=1);

use AutoAltAi\AltTextGenerator\Controller\BackendModuleController;
use AutoAltAi\AltTextGenerator\Controller\BulkRenameController;
use AutoAltAi\AltTextGenerator\Controller\HistoryController;
use AutoAltAi\AltTextGenerator\Controller\SettingsController;

return [
    'media_autoalt_alt_text_generator' => [
        'parent' => 'media',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/media/autoalt-alt-text-generator',
        'iconIdentifier' => 'module-alt-text-generator',
        'labels' => [
            'title' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.title',
            'shortDescription' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.shortDescription',
            'description' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.description',
        ],
        'inheritNavigationComponentFromMainModule' => false,
        'routes' => [
            '_default' => [
                'target' => BackendModuleController::class . '::dashboardAction',
                'methods' => ['GET', 'POST'],
            ],
        ],
    ],

    'media_autoalt_alt_text_generator_bulk' => [
        'parent' => 'media_autoalt_alt_text_generator',
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/media/autoalt-alt-text-generator/bulk-generate',
        'iconIdentifier' => 'module-alt-text-generator',
        'labels' => [
            'title' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.bulk.title',
            'shortDescription' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.bulk.shortDescription',
            'description' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.bulk.description',
        ],
        'inheritNavigationComponentFromMainModule' => false,
        'routes' => [
            '_default' => [
                'target' => BackendModuleController::class . '::bulkGenerateAction',
            ],
        ],
    ],

    'media_autoalt_alt_text_generator_history' => [
        'parent' => 'media_autoalt_alt_text_generator',
        'position' => ['after' => 'media_autoalt_alt_text_generator_rename'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/media/autoalt-alt-text-generator/history',
        'iconIdentifier' => 'module-alt-text-generator',
        'labels' => [
            'title' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.history.title',
            'shortDescription' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.history.shortDescription',
            'description' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.history.description',
        ],
        'inheritNavigationComponentFromMainModule' => false,
        'routes' => [
            '_default' => [
                'target' => HistoryController::class . '::handleRequest',
            ],
            'retry' => [
                'path' => '/retry',
                'target' => HistoryController::class . '::retryEntry',
                'methods' => ['POST'],
            ],
        ],
    ],

    'media_autoalt_alt_text_generator_rename' => [
        'parent' => 'media_autoalt_alt_text_generator',
        'position' => ['after' => 'media_autoalt_alt_text_generator_bulk'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/media/autoalt-alt-text-generator/bulk-rename',
        'iconIdentifier' => 'module-alt-text-generator',
        'labels' => [
            'title' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.rename.title',
            'shortDescription' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.rename.shortDescription',
            'description' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.rename.description',
        ],
        'inheritNavigationComponentFromMainModule' => false,
        'routes' => [
            '_default' => [
                'target' => BulkRenameController::class . '::indexAction',
                'methods' => ['GET'],
            ],
        ],
    ],

    'media_autoalt_alt_text_generator_settings' => [
        'parent' => 'media_autoalt_alt_text_generator',
        'position' => ['after' => 'media_autoalt_alt_text_generator_history'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/media/autoalt-alt-text-generator/settings',
        'iconIdentifier' => 'module-alt-text-generator',
        'labels' => [
            'title' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.settings.title',
            'shortDescription' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.settings.shortDescription',
            'description' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:module.settings.description',
        ],
        'inheritNavigationComponentFromMainModule' => false,
        'routes' => [
            '_default' => [
                'target' => SettingsController::class . '::handleRequest',
                'methods' => ['GET', 'POST'],
            ],
        ],
    ],
];
