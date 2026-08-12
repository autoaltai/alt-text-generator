<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sends the "bulk generation finished" email, matching the AutoAlt.ai WordPress
 * plugin's notification setting. Triggered once, right when a bulk run's
 * remaining eligible count drains to zero.
 */
final readonly class BulkCompletionNotifierService
{
    public function __construct(
        private MailerInterface $mailer,
        private ErrorLogService $errorLogService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     * @param array{total: int, completed: int, failed: int} $progress
     */
    public function notifyIfEnabled(array $configuration, array $progress): void
    {
        if (!$this->isEnabled($configuration['notifyOnBulkComplete'] ?? false)) {
            return;
        }

        $recipient = trim((string)($configuration['notificationEmail'] ?? ''));
        if ($recipient === '' || !GeneralUtility::validEmail($recipient)) {
            return;
        }

        try {
            $mailMessage = GeneralUtility::makeInstance(MailMessage::class);
            $mailMessage
                ->to(new Address($recipient))
                ->subject('AutoAlt.ai: Bulk alt text generation finished')
                ->text(sprintf(
                    "Your AutoAlt.ai bulk generation queue has finished processing.\n\n" .
                    "Total images: %d\nCompleted successfully: %d\nFailed: %d\n",
                    $progress['total'],
                    $progress['completed'],
                    $progress['failed'],
                ));
            $this->mailer->send($mailMessage);
        } catch (\Throwable $exception) {
            $this->logger->warning('AutoAlt.ai could not send the bulk completion notification email.', [
                'recipient' => $recipient,
                'exception' => $exception,
            ]);
            try {
                $this->errorLogService->record('warning', 'Could not send bulk completion notification email: ' . $exception->getMessage(), [
                    'recipient' => $recipient,
                ]);
            } catch (\Throwable $loggingException) {
                $this->logger->warning('AutoAlt.ai notification failure could not be written to the extension error log.', [
                    'recipient' => $recipient,
                    'exception' => $loggingException,
                ]);
            }
        }
    }

    private function isEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }
}
