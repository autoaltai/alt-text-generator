<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Dto\SiteLanguageSelection;
use AutoAltAi\AltTextGenerator\Service\SiteLanguageResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;

final class SiteLanguageResolverTest extends TestCase
{
    public function testSelectionUsesLanguageZeroAsSourceAndKeepsNonSequentialActiveTargets(): void
    {
        $site = new Site('camino', 1, [
            'base' => 'https://example.test/',
            'languages' => [
                ['languageId' => 0, 'locale' => 'en-US', 'title' => 'English'],
                ['languageId' => 1, 'locale' => 'de-DE', 'title' => 'Deutsch'],
                ['languageId' => 2, 'locale' => 'fr-FR', 'title' => 'Français'],
                ['languageId' => 5, 'locale' => 'es-ES', 'title' => 'Español'],
                ['languageId' => 9, 'locale' => 'it-IT', 'title' => 'Italiano', 'enabled' => false],
            ],
        ]);
        $resolver = $this->createResolver();

        $method = new \ReflectionMethod($resolver, 'createSelection');
        /** @var SiteLanguageSelection $selection */
        $selection = $method->invoke($resolver, $site);

        self::assertSame(0, $selection->source->languageId);
        self::assertSame('en', $selection->source->languageCode);
        self::assertSame([1, 2, 5], array_keys($selection->targets));
        self::assertSame('de', $selection->targets[1]->languageCode);
        self::assertSame('fr-FR', $selection->targets[2]->locale);
        self::assertSame('5:es-ES', $selection->targets[5]->historyLanguage());
    }

    public function testSelectionRequiresConfiguredLanguageZero(): void
    {
        $site = new Site('without-default', 1, [
            'base' => 'https://example.test/',
            'languages' => [
                ['languageId' => 5, 'locale' => 'es-ES', 'title' => 'Español'],
            ],
        ]);
        $resolver = $this->createResolver();

        $method = new \ReflectionMethod($resolver, 'createSelection');

        self::assertNull($method->invoke($resolver, $site));
    }

    public function testGermanLanguageZeroIsTheSourceForHindiLanguageOne(): void
    {
        $site = new Site('german-hindi', 1, [
            'base' => 'https://example.test/',
            'languages' => [
                ['languageId' => 0, 'locale' => 'de-DE', 'title' => 'Deutsch'],
                ['languageId' => 1, 'locale' => 'hi-IN', 'title' => 'हिन्दी'],
            ],
        ]);
        $resolver = $this->createResolver();

        $method = new \ReflectionMethod($resolver, 'createSelection');
        /** @var SiteLanguageSelection $selection */
        $selection = $method->invoke($resolver, $site);

        self::assertSame(0, $selection->source->languageId);
        self::assertSame('de-DE', $selection->source->locale);
        self::assertSame('de', $selection->source->languageCode);
        self::assertSame([1], array_keys($selection->targets));
        self::assertSame('hi-IN', $selection->targets[1]->locale);
        self::assertSame('hi', $selection->targets[1]->languageCode);
        self::assertSame('1:hi-IN', $selection->targets[1]->historyLanguage());
    }

    private function createResolver(): SiteLanguageResolver
    {
        /** @var \TYPO3\CMS\Core\Site\SiteFinder $siteFinder */
        $siteFinder = (new \ReflectionClass(\TYPO3\CMS\Core\Site\SiteFinder::class))->newInstanceWithoutConstructor();
        /** @var \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool */
        $connectionPool = (new \ReflectionClass(\TYPO3\CMS\Core\Database\ConnectionPool::class))->newInstanceWithoutConstructor();

        return new SiteLanguageResolver($siteFinder, $connectionPool, new NullLogger());
    }
}
