<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\FileRenameResult;
use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextRequest;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceFactory;

final readonly class AiFilenameService
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private FileGenerateRequestFactory $requestFactory,
        private AutoAltApiService $apiService,
        private FileRenameService $fileRenameService,
        private ConfigurationService $configurationService,
        private SiteLanguageResolver $siteLanguageResolver,
    ) {}

    public function rename(int $fileUid, string $websiteDomain, BackendUserAuthentication $backendUser): FileRenameResult
    {
        if ($this->apiService->getApiKey() === '') {
            return new FileRenameResult(false, $fileUid, message: 'Connect an AutoAlt.ai API key before using AI rename.');
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
            $configuration = $this->configurationService->get();
            if (!$this->requestFactory->isGenerableImage($file)
                || !$this->requestFactory->isAllowedExtension($file->getExtension(), (string)($configuration['allowedImageExtensions'] ?? ''))
            ) {
                return new FileRenameResult(false, $fileUid, message: 'This image type is not supported.', skipped: true);
            }

            $languageSelection = $this->siteLanguageResolver->resolveForFile($fileUid);
            $baseRequest = $this->requestFactory->buildFromFile(
                $file,
                $configuration,
                $websiteDomain,
                languageOverride: $languageSelection?->source->languageCode,
            );
            $metadata = $file->getMetaData();
            $contextKeywords = array_filter([
                trim((string)$metadata->offsetGet('title')),
                trim((string)$metadata->offsetGet('alternative')),
                trim((string)$metadata->offsetGet('description')),
                trim((string)$metadata->offsetGet('caption')),
            ]);
            $seoKeywords = trim(implode(', ', array_unique(array_filter([
                $baseRequest->seoKeywords,
                ...$contextKeywords,
            ]))));

            $request = new GenerateAltTextRequest(
                imageUrl: $baseRequest->imageUrl,
                websiteDomain: $baseRequest->websiteDomain,
                language: $baseRequest->language,
                base64Image: $baseRequest->base64Image,
                writingStyle: $baseRequest->writingStyle,
                seoKeywords: $seoKeywords,
                negativeKeywords: $baseRequest->negativeKeywords,
                prefix: $baseRequest->prefix,
                suffix: $baseRequest->suffix,
                customPrompt: $baseRequest->customPrompt,
                productName: trim((string)$metadata->offsetGet('title')),
                renameFile: true,
                generateAltText: false,
            );
            $generated = $this->apiService->generateAltText($request);
            $filename = trim($generated->filename);
            if ($filename === '') {
                return new FileRenameResult(false, $fileUid, message: 'AutoAlt.ai did not return a filename. The file was not changed.');
            }

            return $this->fileRenameService->rename(
                $fileUid,
                $filename,
                'ai',
                $backendUser,
                $generated->assetId !== null ? (string)$generated->assetId : '',
            );
        } catch (\Throwable $exception) {
            $message = trim((string)preg_replace('/\s+/', ' ', $exception->getMessage()));
            $message = str_replace(
                array_filter([Environment::getProjectPath(), Environment::getPublicPath()]),
                '[internal path]',
                $message,
            );
            return new FileRenameResult(false, $fileUid, message: mb_substr($message !== '' ? $message : 'AI rename failed.', 0, 500));
        }
    }
}
