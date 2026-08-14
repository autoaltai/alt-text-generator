# Alt Text Generator - AutoAlt.ai for TYPO3

Generate AI-powered image alternative text, titles, descriptions, and
SEO-friendly filenames in TYPO3. Use it on one image, a selected Filelist
folder, automatically on upload, or across a media library in bulk.

AutoAlt.ai works inside TYPO3's File Abstraction Layer (FAL), so editors can
review and manage image metadata in their normal TYPO3 workflow. Every
AutoAlt.ai account includes **50 free credits each month**.

## At a glance

| | |
|---|---|
| Extension key | `alt_text_generator` |
| Composer package | `autoaltai/alt-text-generator` |
| TYPO3 | 13.0.0–14.3.99 |
| PHP | 8.2 or later |
| License | GPL-2.0-or-later |

## Why use AutoAlt.ai?

Maintaining high-quality image metadata across a growing media library takes
time. AutoAlt.ai helps editors create an initial draft and keep filenames and
metadata more consistent, while TYPO3 permissions and FAL storage access stay
in control.

It can help you:

- find images with missing or unusually short alternative text;
- generate alternative text, titles, and descriptions in one workflow;
- create more descriptive, SEO-friendly image filenames;
- reduce repetitive metadata work for newly uploaded images; and
- give editors a reviewable starting point rather than replacing editorial
  judgment.

> [!IMPORTANT]
> AI-generated metadata is a draft. Review it in the page context before
> publishing. Decorative images often need empty alternative text, while
> charts, diagrams, functional images, and legally sensitive content may need
> human-written context. The extension does not guarantee accessibility, SEO,
> or legal compliance.

## Features

- **TYPO3 backend module:** Open **Media > AutoAlt.ai** for a dashboard with
  image counts, missing/short alt-text indicators, active defaults, and credit
  balance.
- **Bulk Alt Text Generator:** Process eligible media-library images with live
  browser progress. Choose **Generate**, **Keep**, or **Clear** independently
  for alternative text, title, and description.
- **Single image generation:** Generate text from the FAL metadata form when
  an editor needs to review an individual image.
- **Filelist selection:** Process selected image files or folders from TYPO3
  Filelist.
- **Automatic generation on upload:** Optionally generate metadata for newly
  uploaded supported images.
- **Automatic image rename on upload:** Optionally request an AI filename for
  each newly uploaded supported image. TYPO3 FAL performs the physical rename,
  preserves the file extension, retains file references, and resolves filename
  conflicts with a unique suffix.
- **Bulk Rename Images:** Review weak filenames, rename manually or with AI,
  inspect rename history, and undo supported changes.
- **Editorial controls:** Configure writing style, character range, prefix,
  suffix, SEO keywords, negative keywords, and a custom prompt.
- **History and diagnostics:** Filter generation history, retry failures,
  review results, and use TYPO3's native **System > Log** for errors.
- **Permissions:** Uses TYPO3 FAL mounts and metadata permissions, with
  optional User TSconfig controls for single generation, bulk work, and
  settings access.
- **Multilingual support:** Uses TYPO3 site-language configuration for
  multilingual metadata workflows.

## Installation

Install the extension from the root of your Composer-based TYPO3 project:

```bash
composer require autoaltai/alt-text-generator
vendor/bin/typo3 extension:setup
```

Then sign in to TYPO3 and open:

```text
Media > AutoAlt.ai > Extension Configuration
```

Connect an existing AutoAlt.ai API key, or use the email verification flow to
create/connect an account. Test a small group of images before processing a
full media library or enabling automatic generation.

## Update to 1.0.7

Version 1.0.7 does not require a manual data migration or settings change.
From the root of your TYPO3 project, update the package and refresh TYPO3's
extension setup and caches:

```bash
composer update autoaltai/alt-text-generator --with-all-dependencies
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:warmup
```

After deployment, open **Media > AutoAlt.ai > Extension Configuration** to
confirm the API connection and credit balance, then run one test generation.
Existing settings, history, and API connections are retained.

## Quick first run

1. Install the package and apply the database schema.
2. Open **Media > AutoAlt.ai > Extension Configuration** and connect your
   AutoAlt.ai account.
3. Configure allowed image types and your preferred generation defaults.
4. Open **Bulk Alt Text Generator** and start with a small test batch.
5. Review results in Filelist or **Alt Text History**.
6. Adjust your settings before running a large batch or enabling upload
   automation.

To rename supported image files automatically after upload, enable
**Automatically Rename Uploaded Image Files** in Extension Configuration.
This option is disabled by default. It uses the same image analysis request as
automatic metadata generation when both options are enabled.

Keep the Bulk Alt Text Generator browser tab open while it is running: bulk
progress is driven by browser requests and stops when the tab is closed or the
connection is interrupted.

## Configuration and permissions

Settings are managed in **Media > AutoAlt.ai > Extension Configuration**.
Give settings access only to trusted administrators or media managers.

For granular controls, use User TSconfig:

```typoscript
tx_alttextgenerator.permissions {
    generateSingle = 1
    runBulkGeneration = 1
    manageSettings = 0
}
```

Users also need normal TYPO3 access to the relevant File Mount and to edit
FAL metadata. The extension does not bypass those permissions.

## Privacy and secure use

To generate metadata, the extension sends image content and the relevant
generation settings—such as language, prompt, keywords, and selected
fields—to AutoAlt.ai. Review the [AutoAlt.ai Privacy Policy](https://www.autoalt.ai/privacy-policy/)
and your organisation's data-protection requirements before enabling it.

Never enter or commit API keys, passwords, personal data, or confidential
information in prompts, keywords, filenames, screenshots, tickets, or source
control. Rotate an API key immediately if it is exposed.

## Documentation and support

- [TYPO3 extension documentation](https://docs.typo3.org/p/autoaltai/alt-text-generator/main/en-us/)
- [AutoAlt.ai TYPO3 guide](https://www.autoalt.ai/docs/typo3/)
- [Project website](https://www.autoalt.ai/)
- [Report an issue](https://github.com/autoaltai/alt-text-generator/issues)
- [Packagist package](https://packagist.org/packages/autoaltai/alt-text-generator)
- Support: [support@autoalt.ai](mailto:support@autoalt.ai)

## Development

Run the checks from the extension directory:

```bash
composer test
```

## License

This project is licensed under [GPL-2.0-or-later](LICENSE.txt).
