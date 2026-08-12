<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Middleware;

use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * TYPO3 core's File List module has no extension point for adding buttons to
 * its multi-record-selection toolbar, so the "Generate Alt Text" button is
 * injected client side instead. This middleware only loads that JS module on
 * the File List module itself (media_management), so the injection stays
 * scoped rather than running backend-wide.
 */
final readonly class FileListSelectionButtonMiddleware implements MiddlewareInterface
{
    private const FILE_LIST_MODULE_IDENTIFIER = 'media_management';

    public function __construct(
        private PageRenderer $pageRenderer,
        private PermissionsService $permissionsService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $module = $request->getAttribute('module');
        if (
            $module instanceof ModuleInterface
            && $module->getIdentifier() === self::FILE_LIST_MODULE_IDENTIFIER
            && $this->permissionsService->canUseBulkGeneration($this->getBackendUser())
        ) {
            $this->pageRenderer->addInlineLanguageLabelFile('EXT:alt_text_generator/Resources/Private/Language/locallang.xlf');
            $this->pageRenderer->loadJavaScriptModule('@autoaltai/alt-text-generator/filelist-selection-button.js');
        }

        return $handler->handle($request);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
