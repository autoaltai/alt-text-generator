..  include:: /Includes.rst.txt
..  _start:

====================================
Alt Text Generator - AutoAlt.ai
====================================

:Extension key:
    alt_text_generator

:Package name:
    autoaltai/alt-text-generator

:Version:
    |release|

:Language:
    en

:Author:
    AutoAlt.ai

:License:
    This document is published under the
    `Open Content License <https://www.openhub.net/licenses/opl>`__.

:Rendered:
    |today|

----

Create useful image metadata without leaving TYPO3. AutoAlt.ai scans TYPO3's
File Abstraction Layer (FAL) and can generate image alternative text, titles,
descriptions, and SEO-friendly filenames individually or in bulk. Every
AutoAlt.ai account includes 50 free credits each month.

Quick start
===========

#. Install the package with Composer.
#. Apply the database schema update.
#. Open **Media > AutoAlt.ai > Extension Configuration**.
#. Connect an existing AutoAlt.ai API key or register with your email.
#. Open **Bulk Alt Text Generator**, review the choices, and process a small
   test set before running your full media library.

For the detailed steps, see :doc:`Installation/Index`.

..  important::

    Generated text is a helpful draft, not a guarantee of legal or
    accessibility compliance. Review metadata in the context where the image
    is used. Decorative images often need an empty alternative text, while
    charts, diagrams, and functional images may need human-written context.

**Table of Contents**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Administration/Index
    Troubleshooting/Index
    Changelog/Index

..  Meta Menu

..  toctree::
    :hidden:

    Sitemap
