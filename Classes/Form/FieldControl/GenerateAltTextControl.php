<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Form\FieldControl;

use AutoAltAi\AltTextGenerator\Service\FileGenerateRequestFactory;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Renders the "Generate with AutoAlt.ai" button next to the sys_file_metadata
 * "Alternative Text" field, mirroring how core's own PasswordGenerator field
 * control fills a sibling input via a small dedicated JavaScript module.
 */
final class GenerateAltTextControl extends AbstractNode
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly FileGenerateRequestFactory $generateRequestFactory,
        private readonly PermissionsService $permissionsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        if (!$this->isAlternativeTextMetadataField() || !$this->permissionsService->canUseSingleGeneration($this->getBackendUser())) {
            return [];
        }

        $fileUid = $this->resolveFileUid();
        if ($fileUid <= 0 || !$this->isImageFile($fileUid)) {
            // AutoAlt.ai only generates alt text for images - suppress the button
            // entirely for other sys_file types (videos, PDFs, YouTube links, ...)
            // rather than showing it and failing the request afterwards.
            return [];
        }

        $options = $this->data['renderData']['fieldControlOptions'] ?? [];
        $itemName = (string)$this->data['parameterArray']['itemFormElName'];
        $id = StringUtility::getUniqueId('t3js-alttextgenerator-fieldcontrol-');

        return [
            'iconIdentifier' => 'actions-wand-sparkles',
            'title' => $options['title'] ?? 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:single.button',
            'linkAttributes' => [
                'id' => $id,
                'data-item-name' => $itemName,
                'data-file-uid' => (string)$fileUid,
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autoaltai/alt-text-generator/single-generate-control.js')->instance($id),
            ],
            'additionalInlineLanguageLabelFiles' => [
                'EXT:alt_text_generator/Resources/Private/Language/locallang.xlf',
            ],
        ];
    }

    private function isAlternativeTextMetadataField(): bool
    {
        return ($this->data['tableName'] ?? '') === 'sys_file_metadata'
            && ($this->data['fieldName'] ?? '') === 'alternative'
            // The button always generates the canonical language-0 source.
            // Localized metadata is maintained by the multilingual flow.
            && (int)($this->data['databaseRow']['sys_language_uid'] ?? 0) === 0;
    }

    private function resolveFileUid(): int
    {
        $fileValue = $this->data['databaseRow']['file'] ?? 0;
        if (is_array($fileValue)) {
            $fileValue = $fileValue[0] ?? 0;
        }

        return (int)$fileValue;
    }

    private function isImageFile(int $fileUid): bool
    {
        try {
            return $this->generateRequestFactory->isGenerableImage($this->resourceFactory->getFileObject($fileUid));
        } catch (\Throwable) {
            return false;
        }
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
