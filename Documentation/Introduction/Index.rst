..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
=================

AutoAlt.ai Alt Text Generator connects your TYPO3 installation to the
`AutoAlt.ai <https://www.autoalt.ai/>`__ image description API and generates
accessible, SEO-friendly alt text for the images stored in your File
Abstraction Layer (FAL). It is the TYPO3 counterpart to the existing
AutoAlt.ai plugins for Shopware, WordPress and Magento, adapted to TYPO3's
native backend module and file processing APIs.

..  _features:

Features
========

*   **Backend module** with a dashboard that shows connection status,
    remaining AutoAlt.ai credits, and how many images are missing alt text.
*   **Settings screen** to configure the API key, writing style, prefixes and
    suffixes, SEO/negative keywords, a custom prompt, allowed image
    extensions, request timeout, and logging.
*   **API key validation** directly from the Settings screen.
*   **Credit balance display** (available, used, paid and free credits).
*   **Image scanning** across all FAL storages to find images with missing
    alt text.
*   **Bulk generation** with live AJAX-driven progress, a live generation
    log, and start/stop controls. The interactive bulk page processes
    batches directly from the browser.
*   **Auto-generate on upload**: new images can be described automatically
    as soon as they are added to a storage.
*   **History**: a retained, filterable log of recent generation attempts
    (bulk, upload, selection, or manual), with a one-click,
    synchronous retry for failures.
*   **Logging**: warnings and errors are written to TYPO3's standard logging
    framework, surfaced in the native *System > Log* backend module, and also
    shown in an in-admin Error Logs panel on the Settings page.
*   **Permissions**: fine-grained, TSconfig-based permission flags let
    administrators allow or restrict single-image generation, bulk generation,
    and settings management per backend user group.
*   **Full localization support** via XLIFF language files.

..  _requirements:

Requirements
============

*   TYPO3 14.0 or higher
*   PHP 8.2 or higher
*   An `AutoAlt.ai <https://www.autoalt.ai/account/api-keys>`__ account and
    API key

..  _support:

Support
=======

*   Website: https://www.autoalt.ai/
*   Support: support@autoalt.ai
