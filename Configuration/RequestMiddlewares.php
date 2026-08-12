<?php

declare(strict_types=1);

use AutoAltAi\AltTextGenerator\Middleware\FileListSelectionButtonMiddleware;

return [
    'backend' => [
        'alt-text-generator/filelist-selection-button' => [
            'target' => FileListSelectionButtonMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-module-validator',
            ],
        ],
    ],
];
