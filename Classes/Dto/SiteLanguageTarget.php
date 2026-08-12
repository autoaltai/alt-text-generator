<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class SiteLanguageTarget
{
    public function __construct(
        public int $languageId,
        public string $locale,
        public string $languageCode,
    ) {}

    public function historyLanguage(): string
    {
        return $this->languageId . ':' . $this->locale;
    }
}
