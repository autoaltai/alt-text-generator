<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;

final readonly class FilenameNormalizationService
{
    private const MAX_FILENAME_BYTES = 240;
    private const RESERVED_NAMES = ['.', '..', 'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];

    public function normalize(string $requestedName, string $extension): string
    {
        $requestedName = trim($requestedName);
        if ($requestedName === '' || str_contains($requestedName, '/') || str_contains($requestedName, '\\') || str_contains($requestedName, "\0")) {
            throw new \InvalidArgumentException('Invalid filename. Enter a basename without a path.');
        }
        if (str_contains($requestedName, '..')) {
            throw new \InvalidArgumentException('Path traversal is not allowed in filenames.');
        }

        $extension = strtolower(ltrim(trim($extension), '.'));
        $stem = pathinfo($requestedName, PATHINFO_FILENAME);
        if (pathinfo($requestedName, PATHINFO_EXTENSION) === '') {
            $stem = $requestedName;
        }
        $stem = mb_strtolower(trim($stem), 'UTF-8');
        $stem = (string)preg_replace('/[\s_]+/u', '-', $stem);
        $stem = (string)preg_replace('/[^\p{L}\p{N}-]+/u', '-', $stem);
        $stem = trim((string)preg_replace('/-+/u', '-', $stem), '-. ');
        if ($stem === '' || in_array(mb_strtolower($stem, 'UTF-8'), self::RESERVED_NAMES, true)) {
            throw new \InvalidArgumentException('The filename is empty or reserved after normalization.');
        }

        $extensionBytes = $extension !== '' ? strlen('.' . $extension) : 0;
        $maxStemBytes = self::MAX_FILENAME_BYTES - $extensionBytes;
        while (strlen($stem) > $maxStemBytes) {
            $stem = mb_substr($stem, 0, max(1, mb_strlen($stem, 'UTF-8') - 1), 'UTF-8');
        }
        $stem = rtrim($stem, '-. ');
        if ($stem === '') {
            throw new \InvalidArgumentException('The normalized filename is empty.');
        }

        return $stem . ($extension !== '' ? '.' . $extension : '');
    }

    public function resolveAvailableName(ResourceStorage $storage, Folder $folder, string $filename, string $currentFilename = ''): string
    {
        if (strcasecmp($filename, $currentFilename) === 0 || !$storage->hasFileInFolder($filename, $folder)) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        for ($suffix = 2; $suffix < 10000; ++$suffix) {
            $candidate = $this->normalize($stem . '-' . $suffix, $extension);
            if (!$storage->hasFileInFolder($candidate, $folder)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No available filename could be found.');
    }
}
