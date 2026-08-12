<?php

declare(strict_types=1);

use AutoAltAi\AltTextGenerator\Controller\BulkGenerationAjaxController;
use AutoAltAi\AltTextGenerator\Controller\BulkRenameController;
use AutoAltAi\AltTextGenerator\Controller\ConnectAjaxController;
use AutoAltAi\AltTextGenerator\Controller\HistoryAjaxController;
use AutoAltAi\AltTextGenerator\Controller\SingleGenerateAjaxController;

return [
    'alt_text_generator_rename_manual' => [
        'path' => '/alt-text-generator/rename/manual',
        'target' => BulkRenameController::class . '::manualAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_rename',
    ],
    'alt_text_generator_rename_ai' => [
        'path' => '/alt-text-generator/rename/ai',
        'target' => BulkRenameController::class . '::aiAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_rename',
    ],
    'alt_text_generator_rename_undo' => [
        'path' => '/alt-text-generator/rename/undo',
        'target' => BulkRenameController::class . '::undoAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_rename',
    ],
    'alt_text_generator_single_generate' => [
        'path' => '/alt-text-generator/single/generate',
        'target' => SingleGenerateAjaxController::class . '::generateAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_management',
    ],
    'alt_text_generator_connect_api_key' => [
        'path' => '/alt-text-generator/connect/api-key',
        'target' => ConnectAjaxController::class . '::connectApiKeyAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_settings',
    ],
    'alt_text_generator_connect_clear' => [
        'path' => '/alt-text-generator/connect/clear',
        'target' => ConnectAjaxController::class . '::clearApiKeyAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_settings',
    ],
    'alt_text_generator_connect_send_otp' => [
        'path' => '/alt-text-generator/connect/send-otp',
        'target' => ConnectAjaxController::class . '::sendOtpAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_settings',
    ],
    'alt_text_generator_connect_verify_otp' => [
        'path' => '/alt-text-generator/connect/verify-otp',
        'target' => ConnectAjaxController::class . '::verifyOtpAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_settings',
    ],
    'alt_text_generator_bulk_preview' => [
        'path' => '/alt-text-generator/bulk/preview',
        'target' => BulkGenerationAjaxController::class . '::previewAction',
        'methods' => ['GET'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_bulk',
    ],
    'alt_text_generator_bulk_process' => [
        'path' => '/alt-text-generator/bulk/process',
        'target' => BulkGenerationAjaxController::class . '::processAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_bulk',
    ],
    'alt_text_generator_selection_resolve' => [
        'path' => '/alt-text-generator/selection/resolve',
        'target' => BulkGenerationAjaxController::class . '::resolveSelectionAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_management',
    ],
    'alt_text_generator_selection_process' => [
        'path' => '/alt-text-generator/selection/process',
        'target' => BulkGenerationAjaxController::class . '::processSelectionAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_management',
    ],
    'alt_text_generator_history_update' => [
        'path' => '/alt-text-generator/history/update',
        'target' => HistoryAjaxController::class . '::updateAltTextAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_history',
    ],
    'alt_text_generator_history_update_title' => [
        'path' => '/alt-text-generator/history/update-title',
        'target' => HistoryAjaxController::class . '::updateTitleAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_history',
    ],
    'alt_text_generator_history_update_description' => [
        'path' => '/alt-text-generator/history/update-description',
        'target' => HistoryAjaxController::class . '::updateDescriptionAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_autoalt_alt_text_generator_history',
    ],
];
