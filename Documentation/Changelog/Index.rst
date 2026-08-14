..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

1.0.7
-----

- Restored the AutoAlt.ai backend module placement in TYPO3 13 while retaining
  its TYPO3 14 Media-module placement.
- Made Filelist bulk-generation progress handling compatible with TYPO3 13
  progress-bar elements.
- Updated the single-image metadata control to use the TYPO3 13-compatible
  FormEngine validation API.
- Updated the backend module icon and release/update documentation.
- No manual data migration or configuration change is required. Existing
  AutoAlt.ai settings, history, and API connections remain unchanged.

1.0.6
-----

- Added automatic uninstall cleanup for TYPO3 13 Extension Manager and
  Composer package removal.
- Removes the AutoAlt.ai configuration, generation history, rename history,
  error-log tables, legacy settings, and related TYPO3 system-log entries.
- Reinstalling the extension now starts with newly created, empty tables.

1.0.5
-----

- Added safe plugin data synchronization with AutoAlt.ai.
- Added automatic SEO-friendly filename generation after image upload.
- Added the Automatically Rename Uploaded Image Files setting.
- Improved compatibility with warmed TYPO3 controller caches.

1.0.4
=====

Current stable release.

*   Provides the AutoAlt.ai backend module under **Media > AutoAlt.ai**.
*   Generates alternative text, titles, and descriptions for individual
    images, selected Filelist items, uploads, or bulk media-library batches.
*   Supports per-field **Generate**, **Keep**, and **Clear** actions in the
    Bulk Alt Text Generator.
*   Includes configurable writing style, length, keywords, negative keywords,
    custom instructions, upload automation, and error logging.
*   Adds generation history with filtering, inline review, and retry support.
*   Adds Bulk Rename Images with filename audit, manual and AI-assisted
    renaming, history, and supported undo operations.
*   Adds an opt-in automatic rename workflow for newly uploaded eligible
    images, using TYPO3 FAL to retain file references and resolve conflicts.
*   Respects TYPO3 FAL file mounts and metadata permissions and offers
    TSconfig-based feature permissions.
*   Supports TYPO3 13.0.0 through 14.3.99 and PHP 8.2 or later.

1.0.0
=====

Initial public release of the AutoAlt.ai TYPO3 extension.
