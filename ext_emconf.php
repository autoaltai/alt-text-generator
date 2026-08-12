<?php

declare(strict_types=1);

defined('TYPO3') || die();

$EM_CONF[$_EXTKEY] = [
    'title' => 'AutoAlt.ai Alt Text Generator',
    'description' => 'Generate accessible and SEO-friendly image alt text in TYPO3 with AutoAlt.ai.',
    'category' => 'module',
    'author' => 'AutoAlt.ai',
    'author_company' => 'AutoAlt.ai',
    'author_email' => 'support@autoalt.ai',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.99.99',
            'typo3' => '14.0.0-14.99.99',
            'backend' => '14.0.0-14.99.99',
            'filelist' => '14.0.0-14.99.99',
            'fluid' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
