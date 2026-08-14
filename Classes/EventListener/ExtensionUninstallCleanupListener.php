<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\EventListener;

use AutoAltAi\AltTextGenerator\Service\UninstallCleanupService;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\Event\AfterPackageDeactivationEvent;

/**
 * Handles uninstalls performed by TYPO3's Extension Manager (TYPO3 13).
 */
final readonly class ExtensionUninstallCleanupListener
{
    public function __construct(
        private UninstallCleanupService $uninstallCleanupService,
    ) {}

    #[AsEventListener(
        identifier: 'autoaltai/alt-text-generator/uninstall-cleanup',
        event: AfterPackageDeactivationEvent::class,
    )]
    public function __invoke(AfterPackageDeactivationEvent $event): void
    {
        if ($event->getPackageKey() !== UninstallCleanupService::EXTENSION_KEY) {
            return;
        }

        $this->uninstallCleanupService->cleanup();
    }
}
