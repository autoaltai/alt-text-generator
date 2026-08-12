..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

All settings are managed from the backend module's **Settings** screen
(**Media > AutoAlt.ai > Settings**). The extension deliberately does not
expose duplicate fields in TYPO3's System > Settings module.

..  _configuration-connection:

Connection
==========

..  confval:: apiKey

    :Type: string
    :Default: (empty)

    Your AutoAlt.ai API key. Add, replace, validate, or clear it only from the
    Welcome to AutoAlt.ai connection card in the backend Settings module.
    If a key was committed to source control, copied into a ticket, or exposed
    in logs, rotate it in AutoAlt.ai before entering its replacement.

The AutoAlt.ai API endpoint is fixed by the extension and is not configurable.

..  note::

    Generation requests are sent to AutoAlt.ai. Depending on configuration,
    the payload can include the API key, image public URL or direct image
    contents, website domain, language, SEO keywords, negative keywords,
    custom prompt text, prefix/suffix text, and generated metadata options.

..  confval:: enabled

    :Type: boolean
    :Default: 1

    Enables AutoAlt.ai features in the TYPO3 backend. When disabled, bulk
    generation and auto-generation on upload are both paused.

..  _configuration-generation:

Generation defaults
====================

Generated text always uses the language configured with TYPO3 site language
ID ``0`` as its source. AutoAlt.ai automatically translates the generated
metadata into every other active site language.

..  confval:: writingStyle

    :Type: string (select)
    :Default: default

    The writing style requested from AutoAlt.ai (for example *Friendly*,
    *Professional*, *Technical*, *SEO-optimized*, ...).

..  confval:: altTextMinLength / altTextMaxLength

    :Type: int
    :Default: 100 / 150

    Preferred minimum and maximum number of characters for generated alt
    text.

..  confval:: altTextPrefix / altTextSuffix

    :Type: string
    :Default: (empty)

    Optional text added before/after each generated alt text.

..  _configuration-seo:

SEO guidance
============

..  confval:: seoKeywords / negativeKeywords

    :Type: string
    :Default: (empty)

    Comma-separated keywords AutoAlt.ai should prefer or avoid when
    generating alt text.

..  confval:: customPrompt

    :Type: text
    :Default: (empty)

    Optional extra business or brand context passed to AutoAlt.ai.

..  _configuration-automation:

Automation and filters
=======================

..  confval:: autoGenerateOnUpload

    :Type: boolean
    :Default: 1

    Automatically generate alt text when a supported image is added to a
    FAL storage (upload, drag & drop, etc.).

..  confval:: overwriteExistingAltText

    :Type: boolean
    :Default: 0

    Allow generated text to replace alt text that already exists.

..  confval:: allowedImageExtensions

    :Type: string
    :Default: jpg,jpeg,png,webp,gif,avif

    Comma-separated list of file extensions to process. Leave empty to
    process all supported raster image types.

..  confval:: shortAltTextLength

    :Type: int (select)
    :Default: 40

    Images with alt text shorter than this number of characters are
    reported as "short alt text".

Images are sent directly from TYPO3 to AutoAlt.ai rather than by public URL.
Unreadable files are skipped, and API errors are always recorded in TYPO3's
System Log and the in-admin Error Logs panel.

..  _configuration-notifications:

Notifications
=============

..  confval:: notifyOnBulkComplete

    :Type: boolean
    :Default: 0

    Send an email when a bulk generation run finishes (reaches zero
    remaining eligible images).

..  confval:: notificationEmail

    :Type: string
    :Default: (empty)

    Recipient address for the bulk-completion notification email above.

..  _configuration-permissions:

Permissions
===========

Three permission flags can be set per backend user group via the group's
**TSconfig** field (see :ref:`t3tsconfig:start` for how User TSconfig is
edited). Administrators always have full access regardless of these
settings.

Generation actions that write TYPO3 FAL metadata (single image generation,
bulk generation, File List selection generation, history inline edit, and
retry) additionally require the backend user/group to have TYPO3 table modify
permission for ``sys_file_metadata``.
For non-admin users, interactive generation also respects TYPO3 FAL file
mounts and file permissions; users can only generate for files/folders they
can read and whose metadata they are allowed to edit.

..  code-block:: typoscript

    tx_alttextgenerator.permissions {
        # Allow non-admin editors to generate alt text from the image metadata form.
        generateSingle = 1
        # Allow non-admin editors to run bulk generation and retry failed items.
        runBulkGeneration = 1
        # Non-admin editors cannot change global AutoAlt.ai settings unless enabled here.
        manageSettings = 0
    }
