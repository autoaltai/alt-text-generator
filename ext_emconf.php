<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Alt Text Generator - AutoAlt.ai',
    'description' => 'Generate AI alt text, image titles, descriptions and SEO-friendly filenames automatically or in bulk for TYPO3 websites. Includes 50 free credits every month.',
    'category' => 'module',
    'author' => 'AutoAlt.ai',
    'author_company' => 'AutoAlt.ai',
    'author_email' => 'support@autoalt.ai',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.5',
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
