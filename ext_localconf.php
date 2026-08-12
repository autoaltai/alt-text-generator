<?php

declare(strict_types=1);

use AutoAltAi\AltTextGenerator\Form\FieldControl\GenerateAltTextControl;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\DatabaseWriter;

defined('TYPO3') || die();

// Surface warnings and errors from AutoAlt.ai in TYPO3's native "System > Log" backend module.
$GLOBALS['TYPO3_CONF_VARS']['LOG']['AutoAltAi']['AltTextGenerator']['writerConfiguration'] = [
    LogLevel::WARNING => [
        DatabaseWriter::class => [],
    ],
];

// Register the "Generate with AutoAlt.ai" FormEngine field control button (see Configuration/TCA/Overrides/sys_file_metadata.php).
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1785900000] = [
    'nodeName' => 'alttextGeneratorControl',
    'priority' => 40,
    'class' => GenerateAltTextControl::class,
];
