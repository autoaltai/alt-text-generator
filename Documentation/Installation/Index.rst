..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

This chapter is for TYPO3 administrators. Editors can start at
:doc:`Usage/Index` once the extension is installed and connected.

Install with Composer
=====================

Run the command from the root directory of the Composer-based TYPO3 project:

..  code-block:: bash

    composer require autoaltai/alt-text-generator

Composer installs the package and publishes its TYPO3 public assets. If the
extension is not activated automatically, activate all newly installed
extensions:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

Apply the database schema
=========================

The extension stores its configuration, generation history, error log, and
rename history in TYPO3 database tables. Apply the database changes before
opening the module:

* In the TYPO3 backend, open **Admin Tools > Maintenance > Analyze Database
  Structure** and apply the suggested changes; or
* Run the following command:

  ..  code-block:: bash

      vendor/bin/typo3 database:updateschema

..  important::

    If settings cannot be saved or the module reports that the schema is
    missing, return to the Database Analyzer and apply the extension tables.

Open the module
===============

Sign in as a TYPO3 administrator. In the backend navigation, open:

..  code-block:: text

    Media > AutoAlt.ai > Extension Configuration

The first page shows the connection card and the current configuration.

Connect an existing AutoAlt.ai account
======================================

#. Obtain an API key from your `AutoAlt.ai account
   <https://www.autoalt.ai/account/api-keys>`__.
#. In the **Connect with API key** card, paste the API key.
#. Select **Connect & Save**.
#. Check that the status changes to **Connected** and that a credit balance is
   displayed.

Register or sign in with email
==============================

If you do not already have an API key:

#. Open **Media > AutoAlt.ai > Extension Configuration**.
#. In the **Login / Register** card, enter your email address.
#. Select **Send verification code**.
#. Enter the six-digit code sent to your email.
#. Select **Verify code**.

The extension creates or connects the account and saves the resulting API key
in its TYPO3 configuration.

First-run checklist
===================

Before processing a complete media library:

#. Review the options in :doc:`Configuration/Index`, especially the allowed
   image types, overwrite setting, title/description generation, and keywords.
#. Start with a small set of test images using **Bulk Alt Text Generator**.
#. Review the generated values in the TYPO3 File List or **Alt Text History**.
#. Adjust your instructions if needed.
#. Enable automatic generation on upload only after you are satisfied with the
   output.

Security of API keys
====================

Never commit an AutoAlt.ai API key to Git, a deployment script, a screenshot,
or a support ticket. If it has been exposed, revoke or rotate it in AutoAlt.ai
and save a replacement in the extension configuration.

Upgrading
=========

For every extension update:

#. Update the Composer package.
#. Run ``vendor/bin/typo3 extension:setup``.
#. Run the Database Analyzer or ``vendor/bin/typo3 database:updateschema``.
#. Flush TYPO3 caches if the backend does not show the new assets or module
   changes.

See :doc:`Troubleshooting/Index` when an update does not complete as expected.
