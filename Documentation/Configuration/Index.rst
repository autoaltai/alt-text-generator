..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

All extension settings are managed in **Media > AutoAlt.ai > Extension
Configuration**. They are global extension settings, so give access only to
trusted administrators or backend user groups.

Connection
==========

Use the connection card to add, replace, or remove the AutoAlt.ai API key.
The card also displays the connection state and available credit balance.

..  warning::

    A valid API key and available credits are required for AI generation and
    AI filename generation.

Data sent for generation
=========================

To generate text, the extension sends the image content to the AutoAlt.ai
service together with the generation settings needed for the request. Depending
on the action, this can include the image filename, site language, requested
metadata fields, writing style, character limits, keywords, negative keywords,
prefix/suffix, and custom prompt.

Review the `AutoAlt.ai Privacy Policy <https://www.autoalt.ai/privacy-policy/>`__
and your organisation's data-protection requirements before enabling the
extension. Do not use prompts or metadata to transmit passwords, API keys, or
unnecessary personal or confidential information.

Automation and file selection
=============================

Extension enabled
-----------------

Turns AutoAlt.ai workflows on or off. Disable the extension temporarily to
pause generation without removing its history or settings.

Auto-generate on upload
-----------------------

When enabled, newly added supported files can be processed automatically. Use
a small test library first, because each eligible generation can use credits.

Automatically Rename Uploaded Image Files
-----------------------------------------

This option is disabled by default. When enabled, AutoAlt.ai requests an
SEO-friendly filename for each newly uploaded eligible image. TYPO3 FAL then
renames the physical file while retaining its file extension and keeping TYPO3
file references connected. If the requested filename is already used in the
same folder, the extension creates an available name using a numeric suffix.

When **Auto-generate on upload** is also enabled, one AutoAlt.ai image-analysis
request is used for both metadata and filename generation. Completed FAL rename
attempts are kept in the Bulk Rename Images history; errors are also written to
TYPO3 logs. Test this feature in a non-production folder before enabling it for
a complete media library.

Overwrite existing alternative text
-----------------------------------

When enabled, generation is allowed to replace existing alternative text.
Disable it to protect editor-written text and fill only empty values.

Short alt-text length
---------------------

Choose the threshold used to flag short alternative text in the dashboard and
Bulk Alt Text Generator. A short value is a review signal, not proof that the
text is wrong.

Allowed image extensions
------------------------

Enter a comma-separated list such as:

..  code-block:: text

    jpg,jpeg,png,webp,gif,avif,svg

Only listed file extensions are eligible for generation. Use this setting to
avoid processing formats your editorial workflow does not use.

Generation defaults
===================

Writing style
-------------

Choose the writing style that best matches your editorial tone. The setting
guides the generated output; editors should still review results in context.

Preferred character range
-------------------------

Set the minimum and maximum number of characters requested for alternative
text. The extension shows a recommended range in the backend. Do not force a
description to a target length when the image needs a shorter or longer human
written alternative.

Prefix and suffix
-----------------

Optionally add fixed text before or after generated alternative text. Use this
sparingly: repeating a brand name in every alternative text is usually not
useful for screen-reader users.

Generate title and description
------------------------------

Choose whether the extension may generate TYPO3 image **Title** and
**Description** metadata in addition to alternative text. In a bulk run, the
editor can override this individually with **Generate**, **Keep**, or
**Clear**.

SEO guidance and custom instructions
====================================

SEO keywords
------------

Enter up to six relevant words or short phrases, separated by commas. They
provide optional context to AutoAlt.ai; they do not replace a meaningful image
description.

Negative keywords
-----------------

Enter words or phrases that should be avoided. Do not include the same term in
both keyword fields.

Custom prompt
-------------

Use the custom prompt for stable editorial context, for example:

..  code-block:: text

    Describe the product clearly. Include material and colour only when they
    are visible. Do not make claims that cannot be seen in the image.

Do not put passwords, API keys, personal data, or confidential business data
in a custom prompt.

Bulk completion notification
============================

Enable **Email notification on bulk completion** and provide a recipient
address if an administrator should be notified after a bulk run finishes.
The TYPO3 installation must have working mail transport for this feature.

Error logs
==========

The settings page displays recent AutoAlt.ai errors and provides a **Clear
logs** action. Errors are also sent to TYPO3's standard logging system and can
be reviewed in **System > Log**. See :doc:`Troubleshooting/Index` for common
causes.

Language behaviour
==================

The extension uses the TYPO3 site's default language as the source for
generation and can create translations for active site languages. Existing
editor-written translations are protected unless an overwrite option applies.
Review translated content for terminology, product names, and legal wording.
