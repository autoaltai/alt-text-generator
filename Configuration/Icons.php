<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'module-alt-text-generator' => [
        'provider' => SvgIconProvider::class,
        // The module-specific SVG gives the brand mark the same visual padding
        // as TYPO3's native backend navigation icons.
        'source' => 'EXT:alt_text_generator/Resources/Public/Icons/module-alt-text-generator.svg',
    ],
];
