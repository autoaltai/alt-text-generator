..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-composer:

Composer
========

Install the extension with Composer:

..  code-block:: bash

    composer require autoaltai/alt-text-generator

Then activate it, if not done automatically, via the Extension Manager or
the command line:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

..  _installation-database:

Database
========

The extension ships database tables for generation history and error logs.
Apply the schema with the Database Analyzer in the Install Tool, or via the
command line:

..  code-block:: bash

    vendor/bin/typo3 database:updateschema

The in-admin error log is capped to the latest 200 entries, and the generation
history is capped to the latest 10,000 entries.

..  _installation-api-key:

Connect your AutoAlt.ai account
================================

#.  Create an account and API key at
    https://www.autoalt.ai/account/api-keys.
#.  In the TYPO3 backend, open **Media > AutoAlt.ai**.
#.  Click **Settings**, paste the API key into the Welcome to AutoAlt.ai
    connection card, and click **Connect & Save**. Then configure generation
    defaults and click **Save settings**.

..  warning::

    Do not commit an AutoAlt.ai API key to source control. If a key has already
    been committed, shared, or logged, rotate it in AutoAlt.ai and redeploy the
    replacement in the AutoAlt.ai Settings connection card.

See :ref:`configuration` for a description of every available setting.
