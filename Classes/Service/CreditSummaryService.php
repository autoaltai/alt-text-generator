<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\CreditInformation;
use AutoAltAi\AltTextGenerator\Exception\AutoAltApiException;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Builds the credit summary shown on the Dashboard, Bulk Generate and Settings pages,
 * so all three stay consistent and only call the AutoAlt.ai API once per request.
 */
final readonly class CreditSummaryService
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private AutoAltApiService $autoAltApiService,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function build(LanguageService $languageService, array $configuration, string $websiteDomain): array
    {
        if (trim((string)($configuration['apiKey'] ?? '')) === '') {
            return $this->buildUnavailable(
                'warning',
                $languageService->sL(self::LLL . 'dashboard.credit.needsApiKey'),
                $languageService->sL(self::LLL . 'dashboard.credit.needsApiKey.detail')
            );
        }

        try {
            $creditInformation = $this->autoAltApiService->getCreditInformation(websiteDomain: $websiteDomain);
        } catch (AutoAltApiException|\Throwable $exception) {
            return $this->buildUnavailable(
                'warning',
                $languageService->sL(self::LLL . 'dashboard.credit.unavailable'),
                sprintf($languageService->sL(self::LLL . 'dashboard.credit.lookupFailed'), $exception->getMessage())
            );
        }

        return $this->buildAvailable($languageService, $creditInformation);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAvailable(LanguageService $languageService, CreditInformation $creditInformation): array
    {
        $state = $creditInformation->available > 0 ? 'success' : 'danger';

        return [
            'available' => $this->formatNumber($creditInformation->available),
            'total' => $this->formatNumber($creditInformation->total),
            'used' => $this->formatNumber($creditInformation->used),
            'paidCredit' => $this->formatNumber($creditInformation->paidCredit),
            'freeCredit' => $this->formatNumber($creditInformation->freeCredit),
            'totalPaidCredit' => $this->formatNumber($creditInformation->totalPaidCredit),
            'totalFreeCredit' => $this->formatNumber($creditInformation->totalFreeCredit),
            'usedPaidCredit' => $this->formatNumber($creditInformation->usedPaidCredit),
            'usedFreeCredit' => $this->formatNumber($creditInformation->usedFreeCredit),
            'cardValue' => $this->formatNumber($creditInformation->available),
            'cardDetail' => $creditInformation->available > 0
                ? $languageService->sL(self::LLL . 'dashboard.credit.detail.available')
                : $languageService->sL(self::LLL . 'dashboard.credit.detail.none'),
            'hasCreditData' => true,
            'usagePercentage' => $creditInformation->total > 0
                ? min(100, (int)round(($creditInformation->used / $creditInformation->total) * 100))
                : 0,
            'isLow' => $creditInformation->available > 0 && $creditInformation->available <= 10,
            'state' => $state,
            'status' => $creditInformation->available > 0
                ? $languageService->sL(self::LLL . 'dashboard.credit.status.ready')
                : $languageService->sL(self::LLL . 'dashboard.credit.status.outOfCredits'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnavailable(string $state, string $value, string $detail): array
    {
        return [
            'available' => '-',
            'total' => '-',
            'used' => '-',
            'paidCredit' => '-',
            'freeCredit' => '-',
            'totalPaidCredit' => '-',
            'totalFreeCredit' => '-',
            'usedPaidCredit' => '-',
            'usedFreeCredit' => '-',
            'cardValue' => $value,
            'cardDetail' => $detail,
            'hasCreditData' => false,
            'usagePercentage' => 0,
            'isLow' => false,
            'state' => $state,
            'status' => $value,
        ];
    }

    private function formatNumber(int $value): string
    {
        return number_format($value);
    }
}
