<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class CreditInformation
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public int $available,
        public int $total,
        public int $used,
        public int $paidCredit,
        public int $freeCredit,
        public int $totalPaidCredit,
        public int $totalFreeCredit,
        public int $usedPaidCredit,
        public int $usedFreeCredit,
        public array $rawResponse = [],
    ) {}

    /**
     * @param array<string, mixed> $response
     */
    public static function fromApiResponse(array $response): self
    {
        $paidCredit = (int)($response['credit'] ?? 0);
        $freeCredit = (int)($response['credit_free'] ?? 0);
        $totalPaidCredit = (int)($response['total_credit'] ?? 0);
        $totalFreeCredit = (int)($response['total_credit_free'] ?? 0);
        $usedPaidCredit = (int)($response['used_credit'] ?? 0);
        $usedFreeCredit = (int)($response['used_credit_free'] ?? 0);

        return new self(
            available: $paidCredit + $freeCredit,
            total: $totalPaidCredit + $totalFreeCredit,
            used: $usedPaidCredit + $usedFreeCredit,
            paidCredit: $paidCredit,
            freeCredit: $freeCredit,
            totalPaidCredit: $totalPaidCredit,
            totalFreeCredit: $totalFreeCredit,
            usedPaidCredit: $usedPaidCredit,
            usedFreeCredit: $usedFreeCredit,
            rawResponse: $response,
        );
    }
}
