<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Command;

use AutoAltAi\AltTextGenerator\Service\UninstallCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\AsNonSchedulableCommand;

/**
 * Invoked by the Composer pre-package-uninstall bridge while the extension is
 * still available to TYPO3's dependency injection container.
 */
#[AsCommand('autoalt:cleanup', 'Remove all AutoAlt.ai extension data before uninstalling.')]
#[AsNonSchedulableCommand]
final class UninstallCleanupCommand extends Command
{
    public function __construct(
        private readonly UninstallCleanupService $uninstallCleanupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $droppedTables = $this->uninstallCleanupService->cleanup();

        $output->writeln(
            $droppedTables === []
                ? 'AutoAlt.ai cleanup completed; no extension tables were present.'
                : 'AutoAlt.ai cleanup removed: ' . implode(', ', $droppedTables),
        );

        return Command::SUCCESS;
    }
}
