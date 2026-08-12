<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Exception;

/**
 * Thrown when a queued image's file record or file contents can no longer be
 * read (deleted file, broken storage link). Distinguished from other generation
 * failures so the "Ignore missing images" setting can skip these silently
 * instead of recording them as failures.
 */
final class MissingImageException extends \RuntimeException {}
