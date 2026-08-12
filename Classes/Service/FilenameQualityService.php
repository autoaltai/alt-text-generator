<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

final readonly class FilenameQualityService
{
    /**
     * These rules intentionally mirror the shared AutoAlt.ai filename audit
     * used by the WordPress and Shopware plugins.
     *
     * @return list<string>
     */
    public function getPoorFilenameReasons(string $filename): array
    {
        $reasons = [];
        foreach ($this->buildAuditStems($filename) as $stem) {
            $reasons = [...$reasons, ...$this->auditStem($stem)];
        }

        return array_values(array_unique($reasons));
    }

    public function isPoor(string $filename): bool
    {
        return $this->getPoorFilenameReasons($filename) !== [];
    }

    /** @return list<string> */
    private function buildAuditStems(string $filename): array
    {
        $filename = mb_strtolower(basename(str_replace('\\', '/', trim($filename))), 'UTF-8');
        $base = preg_replace('/(?:\.(?:jpe?g|png|gif|webp|avif|bmp|tiff?|svg))+$/iu', '', $filename);
        $base = trim(is_string($base) ? $base : $filename);
        if ($base === '') {
            return [];
        }

        $stems = [$base];
        $withoutCollisionSuffix = preg_replace('/(?:-\d+)+$/u', '', $base);
        $withoutCollisionSuffix = is_string($withoutCollisionSuffix)
            ? rtrim($withoutCollisionSuffix, ". _-\t\n\r\0\x0B")
            : '';
        if ($withoutCollisionSuffix !== '' && $withoutCollisionSuffix !== $base) {
            $stems[] = $withoutCollisionSuffix;
        }

        return array_values(array_unique($stems));
    }

    /** @return list<string> */
    private function auditStem(string $stem): array
    {
        $reasons = [];
        if (preg_match('/[-_]\d{2,5}x\d{2,5}$/iu', $stem) === 1) {
            $reasons[] = 'generated_size_name';
            $stem = (string)preg_replace('/[-_]\d{2,5}x\d{2,5}$/iu', '', $stem);
        }

        $marketplacePattern = '/(?:[._-]+)(?:ac[._-]+)?(?:sx|sy|sl|ul|ux|uy|sr|ql)\d+_?$/iu';
        if (preg_match($marketplacePattern, $stem) === 1) {
            $reasons[] = 'marketplace_name';
            $stem = rtrim((string)preg_replace($marketplacePattern, '', $stem), ". _-\t\n\r\0\x0B");
        }
        if (preg_match('/^(img|dsc|dscn|pxl|dcim|sam|mvimg)[_-]?\d+/iu', $stem) === 1) {
            $reasons[] = 'camera_name';
        }
        if (preg_match('/^(screen[-_ ]?shot|screenshot)/iu', $stem) === 1) {
            $reasons[] = 'screenshot_name';
        }
        if (preg_match('/^(img|image|photo|picture|pic|download|untitled|file|asset|media|upload|new[-_ ]?image|product[-_ ]?image|whatsapp[-_ ]?image|telegram[-_ ]?image)([-_ ]?\d+)?$/iu', $stem) === 1) {
            $reasons[] = 'generic_name';
        }
        if (
            preg_match('/^\d+$/u', $stem) === 1
            || preg_match('/^[\d_-]{5,}$/u', $stem) === 1
            || preg_match('/^\d+(?:[_-]\d+)+$/u', $stem) === 1
        ) {
            $reasons[] = 'numeric_name';
        }
        if (
            preg_match('/^(?:[a-f0-9]{16,}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})$/iu', $stem) === 1
            || (preg_match('/^\d{2,}[a-z0-9]{6,}$/iu', $stem) === 1 && preg_match('/[a-z]/iu', $stem) === 1)
        ) {
            $reasons[] = 'random_name';
        }

        return array_values(array_unique($reasons));
    }
}
