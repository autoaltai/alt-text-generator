<?php

defined('TYPO3') || die();

$EM_CONF[$_EXTKEY] = [
    'title' => 'AutoAlt.ai Alt Text Generator',
    'description' => 'Generate accessible and SEO-friendly image alt text in TYPO3 with AutoAlt.ai.',
    'category' => 'module',
    'author' => 'AutoAlt.ai',
    'author_company' => 'AutoAlt.ai',
    'author_email' => 'support@autoalt.ai',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.99.99',
            'typo3' => '13.0.0-14.3.99',
            'backend' => '13.0.0-14.3.99',
            'filelist' => '13.0.0-14.3.99',
            'fluid' => '13.0.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
