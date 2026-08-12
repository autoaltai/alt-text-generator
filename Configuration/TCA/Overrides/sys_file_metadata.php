<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Extend the existing core "alternative" column in place - addTCAcolumns() would
// shallow-merge and overwrite core's label/type/size config for this column, since
// it already exists. Only inject our fieldControl into the existing config array.
$GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config']['fieldControl']['alttextGenerator'] = [
    'renderType' => 'alttextGeneratorControl',
    'options' => [
        'title' => 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:single.button',
    ],
];
