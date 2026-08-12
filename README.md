# AutoAlt.ai Alt Text Generator for TYPO3

Generate accessible, SEO-friendly image alt text for TYPO3 media assets using
the [AutoAlt.ai](https://www.autoalt.ai/) API.

- **Extension key:** `alt_text_generator`
- **Composer package:** `autoaltai/alt-text-generator`
- **TYPO3:** 14.0+
- **PHP:** 8.2+

## Features

- Backend module (**Media > AutoAlt.ai**) with a dashboard, credit balance,
  and image scan overview
- Settings screen with live API key validation
- Bulk generation with live AJAX progress
- Auto-generate alt text on upload
- Single-image generation from the FAL metadata form
- File List multi-selection generation
- Filterable generation history with one-click retry and automatic retention
- Logging integration with TYPO3's native System Log
- TSconfig-based permissions for single-image generation, bulk generation,
  and settings management
- Full localization support (XLIFF)

## Installation

```bash
composer require autoaltai/alt-text-generator
```

Then apply the database schema (Install Tool > Database Analyzer, or
`vendor/bin/typo3 database:updateschema`) and configure your AutoAlt.ai API
key under **Media > AutoAlt.ai > Settings** in the Welcome to AutoAlt.ai
connection card. Do not commit an API key to source control; rotate a key that
was previously committed, shared, or logged.

## Documentation

Full documentation lives in [`Documentation/`](Documentation/Index.rst) and
will be published to
[docs.typo3.org](https://docs.typo3.org/p/autoaltai/alt-text-generator/main/en-us/)
once the extension is released on TER.

## Support

- Website: https://www.autoalt.ai/
- Support: support@autoalt.ai

## License

GPL-2.0-or-later
