..  include:: /Includes.rst.txt
..  _usage:

=====
Usage
=====

The extension adds **Media > AutoAlt.ai** to the TYPO3 backend. The module is
available to backend users who have the module and file permissions described
in :ref:`configuration-permissions`.

AutoAlt.ai sends an eligible image to the AutoAlt.ai service and writes the
result to the selected FAL metadata fields. Always review generated content
when accuracy is important, especially for informative, legal, medical, or
product images.

..  _usage-dashboard:

Start at the dashboard
======================

Open **Media > AutoAlt.ai > Bulk Alt Text Generator**. The dashboard shows:

* the total number of images and the number missing alt text;
* images with short alt text, based on the configured threshold;
* the available AutoAlt.ai credit balance; and
* the current actions for alt text, title, and description.

Use the action choices to decide what happens when a field is encountered:

* **Generate** replaces that field with a new AI-generated value;
* **Keep** leaves the current value unchanged; and
* **Clear** removes the current value.

Choose **Generate** for Alternative Text when you want missing descriptions
created. A safe starting point is **Keep** for Title and Description, then
enable generation for those fields after reviewing a small batch.

Add optional **SEO keywords** and **negative keywords** for the current run.
Separate phrases with commas and use only terms that genuinely describe the
image or its page context.

..  _usage-bulk-generate:

Generate metadata in bulk
=========================

#. Select the actions for **Alt Text**, **Title**, and **Description**.
#. Optionally enter SEO keywords and negative keywords.
#. Select any applicable filters, such as processing images with existing alt
   text, skipping images already processed by AutoAlt.ai, or limiting the run
   to images whose alt text is shorter than the configured threshold.
#. Review the number shown on the **Generate** button and start the batch.
#. Keep the browser tab open until the progress panel reports completion.
#. Review generated records in **Media > Filelist** or in
   **Media > AutoAlt.ai > Alt Text History**.

Bulk processing is browser-driven AJAX processing. Closing the tab, losing
the browser connection, or putting the computer to sleep stops further
requests. Start the batch again to continue with images that remain eligible.

Use a small first batch whenever you change a prompt, keyword, language, or
field action. This is the quickest way to verify the results before processing
the entire media library.

..  _usage-single-generate:

Generate one image
==================

Open an image metadata record in **Media > Filelist**. The Alternative Text
field has a **Generate with AutoAlt.ai** control for users who have permission
to use it. Generate the text, review it, and save the metadata record.

This workflow is useful for important editorial images where the editor wants
to choose the final wording themselves.

..  _usage-filelist-selection:

Use the Filelist action
=======================

In **Media > Filelist**, select one or more image files or folders and choose
the AutoAlt.ai bulk-generation action. Folder selections are expanded to their
eligible image files. Unsupported extensions and files that the editor cannot
modify are skipped.

This is helpful when an editor has just uploaded a campaign folder and wants
to process only that folder rather than the whole media library. Keep the
browser tab open until the progress indicator completes.

..  _usage-upload-automation:

Generate automatically on upload
================================

When **Auto-generate on upload** is enabled in the extension configuration,
eligible images receive generated metadata when they are added to a TYPO3 FAL
storage. Existing alternative text is preserved unless **Overwrite existing
alt text** is also enabled.

Enable **Automatically Rename Uploaded Image Files** to create an
AI-generated, lowercase, hyphenated filename for eligible new uploads. The
extension preserves the original file extension and lets TYPO3 FAL keep file
references intact. A duplicate filename receives an available numeric suffix.
This option is off by default; test it with a small upload folder before
enabling it for editors.

Enable this only after testing the configured prompt and field behavior. It is
best suited to media libraries with a consistent editorial process.

..  _usage-rename:

Rename image files with AI
==========================

Open **Media > AutoAlt.ai > Bulk Rename Images** to review image filenames
and generate SEO-friendly replacements. You can filter the list, select files,
and either rename manually or request an AI name. The preview/audit view helps
identify poorly named files before making changes.

Before running a batch:

* Verify that the suggested filename accurately represents the image.
* Keep names concise, meaningful, and free of keyword stuffing.
* Confirm that the current backend user has permission to modify the storage.
* Consider testing with a few files first.

TYPO3's FAL references remain connected after a supported rename. The module
records rename activity in its history and provides an undo option where the
previous filename is available. Read-only or unsupported files are skipped.

..  _usage-history:

Review history and retry failures
=================================

**Media > AutoAlt.ai > Alt Text History** lists generation attempts with their
source, status, language, generated values, and error information. Use the
filters to find a specific task. Failed generation attempts can be retried,
and metadata values can be reviewed and edited inline.

History is an operational record, not a substitute for normal TYPO3 backups.
Keep regular backups before major metadata or filename clean-up campaigns.

..  _usage-language:

Use site languages deliberately
===============================

The extension uses TYPO3 site-language information for multilingual work.
Configure the site languages in TYPO3 first, then test a representative image
in each language. Review translations and brand terms before bulk processing;
AI output should be editorially checked in every language.

..  _usage-credits:

Credits and safe operation
==========================

The dashboard displays the credit balance returned by AutoAlt.ai. Generation
and AI-assisted file operations can consume credits. Review the remaining
balance before a large batch and stop the process if results are not suitable.

Do not place API keys, user credentials, or other secrets in keywords, prompts,
filenames, or image metadata.
