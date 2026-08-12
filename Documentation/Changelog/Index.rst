..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

..  _changelog-0-1-0:

0.1.0
=====

Initial release.

*   Backend module with dashboard, credit balance, and image scan overview.
*   Settings screen with API key validation.
*   Bulk generation with live AJAX progress and a live generation log.
*   Auto-generate alt text on upload.
*   File List multi-selection generation for selected files and folders.
*   Generated alt text, title, and description writes use the exact FAL
    metadata row where available.
*   Filterable generation history with synchronous per-entry retry and
    automatic retention, plus inline metadata editing.
*   FAL file mount and metadata edit permissions are enforced before
    generation or metadata writes.
*   Logging integration with TYPO3's System Log, plus an in-admin Error Logs
    panel on the Settings page.
*   TSconfig-based permissions for single-image generation, bulk generation,
    and settings management.
*   Full localization support (XLIFF).
