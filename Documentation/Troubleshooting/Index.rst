..  include:: /Includes.rst.txt
..  _troubleshooting:

===============
Troubleshooting
===============

Start with the extension's **Alt Text History** and TYPO3's **System > Log**.
They normally identify the affected file and the reason a request failed.

The extension cannot connect to AutoAlt.ai
==========================================

#. Open **Media > AutoAlt.ai > Extension Configuration**.
#. Verify the API key or reconnect through the email verification flow.
#. Confirm that the server can make outbound HTTPS connections to the
   AutoAlt.ai service.
#. Check TYPO3's **System > Log** and the extension error-log panel.
#. If a key may have been shared or committed, rotate it before trying again.

No images appear in a bulk run
==============================

Check the following:

* The image extension is in the allowed-image-extension setting.
* The selected filters do not exclude the image, for example because it was
  already processed or already has alternative text.
* The current backend user has a File Mount for the storage and permission to
  edit its metadata.
* The image is a supported file in a writable storage.
* There is sufficient AutoAlt.ai credit balance.

Bulk progress stops
===================

Bulk workflows require an open browser tab. Keep the page open and avoid
browser sleep, network disconnects, or navigating away while processing. If it
stops, inspect History/System Log, then start a new batch; the filters can skip
items that completed successfully.

Generated text is not suitable
===============================

Use a small test batch and adjust the configuration:

* refine the writing style, prefix, suffix, or custom prompt;
* use accurate SEO and negative keywords sparingly;
* choose **Keep** for Title or Description if you only want alt text;
* set a sensible length range; and
* review language configuration and sample output for each site language.

Generated content is a draft. It must be reviewed by the responsible editor
and does not by itself guarantee accessibility, legal, or SEO compliance.

File rename fails or is skipped
===============================

Confirm that the file is in a writable FAL storage and that the backend user
has the required storage access. Unsupported files and read-only storages are
skipped. Review the rename history and TYPO3 System Log for the specific
reason, then retry after correcting the permission or storage issue.

Where to get help
=================

Before contacting support, collect the TYPO3 version, extension version,
relevant history entry, timestamp, and the exact error message. Do not include
an API key or other credentials.

* Documentation: https://www.autoalt.ai/docs/typo3/
* Issue tracker: https://github.com/autoaltai/alt-text-generator/issues
* Support: support@autoalt.ai
