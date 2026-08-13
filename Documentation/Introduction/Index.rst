..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

What AutoAlt.ai does
====================

Alt Text Generator - AutoAlt.ai connects TYPO3's media library to the
`AutoAlt.ai <https://www.autoalt.ai/>`__ image-description service. It helps
editors find images that need attention and create a first draft of their
metadata directly in the TYPO3 backend.

The extension works with TYPO3 FAL metadata. It does not require editors to
download images or leave their normal media workflow.

Benefits
========

* **Save editorial time:** create metadata for one image, selected files, or
  an entire eligible media library.
* **Improve media quality:** find missing and unusually short alternative
  text, and identify weak filenames.
* **Keep control:** choose independently whether to generate, keep, or clear
  alternative text, title, and description in a bulk run.
* **Support consistent output:** use a writing style, character range,
  keywords, negative keywords, a custom prompt, and optional prefix/suffix.
* **Work safely in TYPO3:** permissions, FAL file mounts, metadata edit
  permissions, history, logs, and reversible filename changes are respected.
* **Support multilingual sites:** generated metadata can be translated for
  active TYPO3 site languages.

Main features
=============

Dashboard
---------

The dashboard shows the extension and connection status, available credits,
the number of indexed images, images without alternative text, images with
short alternative text, and active generation defaults.

Bulk Alt Text Generator
-----------------------

Process eligible images with live browser progress. For each bulk run, select
an action for alternative text, title, and description:

* **Generate** creates a new value with AutoAlt.ai.
* **Keep** leaves the current value unchanged.
* **Clear** removes the current value.

You can override SEO and negative keywords for one run, process only short
alternative text, skip files already processed by AutoAlt.ai, or choose to
overwrite existing metadata.

Single-image and File List actions
----------------------------------

Editors can generate metadata from the FAL metadata form. The TYPO3 File List
also provides a multi-selection action for selected files and folders.

Automatic generation on upload
------------------------------

When enabled, supported images added to a FAL storage can be processed
automatically. Existing editor-written alternative text remains unchanged
unless overwriting is enabled.

An optional, disabled-by-default upload setting can also generate an
SEO-friendly filename. TYPO3 FAL performs the physical rename and keeps file
references connected.

Bulk Rename Images
------------------

The filename audit finds weak filenames. Editors can rename one image
manually, request an AI filename, process selected or visible images, and undo
the most recent supported rename from the rename history.

History and logs
----------------

The history records generation attempts and allows inline review or edits of
generated metadata. Failed entries can be retried. API errors are also
available in the extension settings and TYPO3's standard **System > Log**
module.

Requirements
============

* TYPO3 13.0.0 through 14.3.99
* PHP 8.2 or later
* Composer-based TYPO3 installation
* An AutoAlt.ai account and a valid API key, or access to email registration
* Network access from the TYPO3 server to the AutoAlt.ai service

Supported image types
=====================

The default setting includes ``jpg``, ``jpeg``, ``png``, ``webp``, ``gif``,
``avif``, and ``svg``. Administrators can change the allowed list in
:doc:`Configuration/Index`.

Accessibility and SEO
=====================

Good alternative text depends on the image's purpose in the page or content
element. AutoAlt.ai helps create and review drafts, but editorial review
remains important:

* Leave decorative images empty when they do not convey information.
* Describe the purpose of functional images, such as buttons or links.
* Add human-written explanations for complex charts, diagrams, and images that
  require context not present in the image.
* Use keywords only when they are relevant; do not use the extension for
  keyword stuffing.

Privacy and support
===================

Review the `AutoAlt.ai Privacy Policy <https://www.autoalt.ai/privacy-policy/>`__
and your organisation's data-protection requirements before enabling
generation. For product help, see the `AutoAlt.ai TYPO3 guide
<https://www.autoalt.ai/docs/typo3/>`__. For defects or feature requests, use
the `GitHub issue tracker <https://github.com/autoaltai/alt-text-generator/issues>`__.
