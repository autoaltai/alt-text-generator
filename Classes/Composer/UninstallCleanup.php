<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Composer;

use Composer\Installer\PackageEvent;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Root Composer projects can register this callback for pre-package-uninstall.
 * Composer only runs scripts declared by the root project, never scripts from
 * dependencies, which is why this bridge is registered by the project.
 */
final class UninstallCleanup
{
    public static function onPrePackageUninstall(PackageEvent $event): void
    {
        if ($event->getOperation()->getPackage()->getName() !== 'autoaltai/alt-text-generator') {
            return;
        }

        $vendorDirectory = (string)$event->getComposer()->getConfig()->get('vendor-dir');
        $typo3Binary = $vendorDirectory . '/bin/typo3';
        if (!is_file($typo3Binary)) {
            throw new RuntimeException('Cannot run AutoAlt.ai uninstall cleanup: TYPO3 CLI binary was not found.');
        }

        $process = new Process([PHP_BINARY, $typo3Binary, 'autoalt:cleanup'], dirname($vendorDirectory));
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'AutoAlt.ai uninstall cleanup failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()),
            );
        }
    }
}
