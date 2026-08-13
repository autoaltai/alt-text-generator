..  include:: /Includes.rst.txt
..  _administration:

==============
Administration
==============

This chapter is for TYPO3 administrators who install, configure, monitor, and
grant access to the extension.

..  _configuration-permissions:

Backend access and permissions
==============================

Grant a backend user group access to **Media > AutoAlt.ai** in the usual TYPO3
backend module permissions. Users also need access to the relevant FAL storage
and permission to edit file metadata. The extension does not bypass TYPO3 file
mount or metadata permissions.

For more granular control, add the following Page TSconfig to a backend user
or group configuration:

..  code-block:: typoscript

    tx_alttextgenerator.permissions {
        generateSingle = 1
        runBulkGeneration = 1
        manageSettings = 0
    }

The values are booleans:

* **generateSingle** permits the one-image generation control in Filelist.
* **runBulkGeneration** permits bulk generation workflows and retries.
* **manageSettings** permits access to the extension settings screens.

Set permissions to ``0`` where the user should not perform that task. Users
must still have the ordinary TYPO3 permissions required for the affected files
and metadata records.

Recommended editor roles
------------------------

* **Content editor:** Filelist access and ``generateSingle = 1``.
* **Media manager:** the above plus ``runBulkGeneration = 1`` for the media
  folders they manage.
* **Administrator or trusted media lead:** ``manageSettings = 1`` and access
  to the AutoAlt.ai API configuration.

..  _administration-api:

API connection and credentials
==============================

Open **Media > AutoAlt.ai > Extension Configuration** to connect the site to
AutoAlt.ai. A user can connect with an existing API key or create/connect an
account through the email verification flow.

Treat the API key as a secret:

* Enter it only in the TYPO3 configuration screen or a secure deployment
  configuration.
* Never commit it to Git, documentation, screenshots, tickets, or browser
  console logs.
* Rotate the key immediately if it is exposed.
* Remove or replace the key before transferring a site to another team.

..  _administration-rollout:

Recommended rollout
===================

#. Install the extension and apply the database schema.
#. Connect a test or production API key.
#. Configure allowed file extensions, writing style, length, and optional
   keywords.
#. Test one or two representative images in every required language.
#. Run a small batch and have an editor review the output.
#. Enable upload automation only when results are consistently suitable.
#. Give bulk and settings permissions only to users who need them.

This staged rollout keeps metadata quality under editorial control and helps
avoid accidental changes to an entire library.

..  _administration-logging:

Logging and monitoring
======================

Enable API-error logging in the extension configuration when diagnosing
connection or generation problems. Entries are written through TYPO3's logging
framework and can be reviewed in **System > Log**. The extension also displays
recent entries in its Settings error-log panel, where they can be cleared after
review.

Check the following after a deployment or configuration change:

* API connection status and credit balance;
* a single test generation;
* TYPO3's **System > Log** for errors; and
* the extension history for unexpected failures or skipped files.

..  _administration-upgrades:

Upgrades and backups
====================

Before an extension update, back up the database and application files using
your normal TYPO3 deployment process. After updating Composer dependencies,
apply any database changes in the Install Tool or with:

..  code-block:: bash

    vendor/bin/typo3 database:updateschema

Clear TYPO3 caches, verify the connection, and run a small generation test
before starting a production batch. Review the release notes and the
:ref:`changelog` for each update.
