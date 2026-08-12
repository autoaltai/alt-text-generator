<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

/**
 * The active, site-specific language configuration used for one FAL file.
 * Locale values come directly from TYPO3 SiteLanguage objects; the API uses
 * their ISO language code while history keeps the fuller locale for diagnosis.
 */
final readonly class SiteLanguageSelection
{
    /**
     * @param array<int, SiteLanguageTarget> $targets keyed by sys_language_uid
     */
    public function __construct(
        public SiteLanguageTarget $source,
        public array $targets,
    ) {}
}
