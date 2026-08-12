..  include:: /Includes.rst.txt
..  _usage:

=====
Usage
=====

The extension adds a new backend module under **Media > AutoAlt.ai**,
visible to any backend user or group granted access to it (see
:ref:`configuration-permissions`).

..  _usage-dashboard:

Dashboard
=========

The dashboard is the module's landing page and shows:

*   Overview cards for connection status, whether the extension is enabled,
    upload automation, remaining credits, and images missing alt text.
*   A **Generation Workbench** panel listing a sample of images that are
    still missing alt text.
*   A **Credit Balance** panel showing available, used, paid and free
    AutoAlt.ai credits.
*   A **Current Defaults** panel summarizing the active configuration.

..  _usage-bulk-generate:

Bulk generation and progress
=============================

The **Bulk Generate** page (**Media > AutoAlt.ai > Bulk Generate**) runs
as an interactive browser-driven workflow:

#.  Optionally set **SEO Keywords** / **Negative Keywords** for this run, and
    choose filters: **Include images with existing alt text and overwrite
    them**, **Skip images already processed by AutoAlt.ai**, and/or
    **Generate alt text only for images with short alt text**. The
    **Generate alt text for N images** button's count updates live as you
    change these.
#.  Click **Generate alt text for N images**. The page repeatedly requests the
    next eligible image (respecting the configured allowed extensions),
    updating the progress bar and a live
    generation log - one row per image, with its result or error - without
    reloading the page.
#.  Click **Stop** at any time to finish the current batch and halt. Since
    this interactive run does not resume automatically - simply click
    **Generate Alt Text** again to pick up any images still missing alt text.

Because each batch is a plain AJAX request driven by the open browser tab,
a run only progresses while that tab stays open.

..  _usage-single-generate:

Single image generation
=======================

Editors with the required permissions can generate alt text directly from the
FAL metadata form. Open an image metadata record, use the **Generate with
AutoAlt.ai** control beside the alternative text field, review the generated
alt text/title/description, and save the metadata record.

..  _usage-filelist-selection:

File List selection generation
==============================

In TYPO3's **File List** module, editors with File List access and AutoAlt.ai
bulk metadata permissions can select images or folders and use the
**Generate Alt Text - AutoAlt.ai** multi-selection action. Folders are
expanded to matching image files, unsupported file types are skipped, and the
browser tab processes the selection in batches.

Existing alt text is overwritten for this selection workflow. Keep the browser
tab open until the progress indicator finishes.

..  _usage-upload-automation:

Auto-generation on upload
==========================

When **Auto-generate on upload** is enabled (see :ref:`configuration`), any
image uploaded to a FAL storage is described automatically and immediately,
using the same settings as bulk generation. This mirrors the "generate on
upload" behaviour of the AutoAlt.ai WordPress, Shopware and Magento plugins.

Existing alt text is preserved unless **Overwrite existing alt text** is
enabled.

..  _usage-history:

History
=======

**Media > AutoAlt.ai > History** lists retained generation attempts - from
bulk generation, File List selection generation, upload automation, manual
generation, or a retry - with their source, language, status, and either the
generated alt text or the error message. The latest 10,000 history entries are
kept automatically. Use the status and source filters to narrow the list, and
click **Retry** on a failed entry to regenerate that image immediately.

..  _usage-logging:

Logging
=======

When **Log API errors** is enabled, warnings and errors raised while
talking to the AutoAlt.ai API are written to TYPO3's logging framework
under the `AutoAlt.ai\\AltTextGenerator` channel and forwarded to the
`sys_log` table, so they appear in the native **System > Log** backend
module alongside all other TYPO3 log entries. The same entries are also
shown in the **Error Logs** panel on **Media > AutoAlt.ai > Settings**,
with a **Clear logs** action to reset it.
