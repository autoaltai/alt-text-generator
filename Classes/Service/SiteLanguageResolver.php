<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\SiteLanguageSelection;
use AutoAltAi\AltTextGenerator\Dto\SiteLanguageTarget;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\Site\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves the site configuration relevant to a FAL file without treating a
 * system-wide language list as site configuration. A file can be shared across
 * sites; conflicting site configurations are deliberately considered unsafe.
 */
final readonly class SiteLanguageResolver
{
    public function __construct(
        private SiteFinder $siteFinder,
        private ConnectionPool $connectionPool,
        private LoggerInterface $logger,
    ) {}

    public function resolveForFile(int $fileUid): ?SiteLanguageSelection
    {
        if ($fileUid <= 0) {
            return null;
        }

        try {
            $sites = $this->resolveSitesForFile($fileUid);
        } catch (\Throwable $exception) {
            $this->logger->warning('AutoAlt.ai skipped multilingual metadata because site resolution failed.', [
                'fileUid' => $fileUid,
                'exception' => $exception,
            ]);
            return null;
        }
        if ($sites === []) {
            $this->logger->warning('AutoAlt.ai skipped multilingual metadata because no site could be resolved for the file.', [
                'fileUid' => $fileUid,
            ]);
            return null;
        }

        $selections = [];
        foreach ($sites as $site) {
            $selection = $this->createSelection($site);
            if ($selection === null) {
                $this->logger->warning('AutoAlt.ai skipped multilingual metadata because the resolved site has no language-0 source.', [
                    'fileUid' => $fileUid,
                    'siteIdentifier' => $site->getIdentifier(),
                ]);
                return null;
            }
            $selections[$this->selectionFingerprint($selection)] = $selection;
        }

        // More than one distinct language configuration cannot be applied to
        // global FAL metadata without choosing a site arbitrarily.
        if (count($selections) !== 1) {
            $this->logger->warning('AutoAlt.ai skipped multilingual metadata because the file is shared by sites with conflicting languages.', [
                'fileUid' => $fileUid,
                'siteIdentifiers' => array_keys($sites),
            ]);
            return null;
        }

        return reset($selections);
    }

    /**
     * @return array<string, Site>
     */
    private function resolveSitesForFile(int $fileUid): array
    {
        $allSites = $this->siteFinder->getAllSites();
        if (count($allSites) === 1) {
            return $allSites;
        }

        $sites = [];
        foreach ($this->findReferencedPageIds($fileUid) as $pageId) {
            try {
                $site = $this->siteFinder->getSiteByPageId($pageId);
                $sites[$site->getIdentifier()] = $site;
            } catch (SiteNotFoundException) {
                continue;
            }
        }

        return $sites;
    }

    /**
     * sys_file_reference points either straight at a page or at a record with
     * a pid (most commonly tt_content). This is only site detection; all
     * metadata writes still use TYPO3 Core APIs.
     *
     * @return array<int, int>
     */
    private function findReferencedPageIds(int $fileUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $references = $queryBuilder
            ->select('tablenames', 'uid_foreign')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $pageIds = [];
        foreach ($references as $reference) {
            $table = (string)($reference['tablenames'] ?? '');
            $uid = (int)($reference['uid_foreign'] ?? 0);
            if ($table === '' || $uid <= 0) {
                continue;
            }
            if ($table === 'pages') {
                $pageIds[] = $uid;
                continue;
            }

            // Only accept a TCA-backed table with a pid column. This prevents
            // a table name stored in a reference from becoming arbitrary SQL.
            if (!isset($GLOBALS['TCA'][$table]['columns']['pid'])) {
                continue;
            }
            $recordQueryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
            $pageId = $recordQueryBuilder
                ->select('pid')
                ->from($table)
                ->where($recordQueryBuilder->expr()->eq('uid', $recordQueryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne();
            if ((int)$pageId > 0) {
                $pageIds[] = (int)$pageId;
            }
        }

        return array_values(array_unique($pageIds));
    }

    private function createSelection(Site $site): ?SiteLanguageSelection
    {
        try {
            $source = $this->toTarget($site->getLanguageById(0));
        } catch (\InvalidArgumentException) {
            return null;
        }

        $targets = [];
        foreach ($site->getLanguages() as $language) {
            if ($language->getLanguageId() === 0) {
                continue;
            }
            $target = $this->toTarget($language);
            $targets[$target->languageId] = $target;
        }

        return new SiteLanguageSelection($source, $targets);
    }

    private function toTarget(SiteLanguage $language): SiteLanguageTarget
    {
        return new SiteLanguageTarget(
            languageId: $language->getLanguageId(),
            locale: $language->getLocale()->getName(),
            languageCode: $language->getLocale()->getLanguageCode(),
        );
    }

    private function selectionFingerprint(SiteLanguageSelection $selection): string
    {
        $parts = [$selection->source->languageId . ':' . $selection->source->locale];
        foreach ($selection->targets as $target) {
            $parts[] = $target->languageId . ':' . $target->locale;
        }

        return implode('|', $parts);
    }
}
