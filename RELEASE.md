# Releasing version 1.0.7

## Included changes

- Restores TYPO3 13 compatibility for the backend module, Filelist bulk
  progress bar, and single-image metadata control.
- Retains the TYPO3 14 Media-module placement.
- Updates the backend module icon.
- TYPO3 Extension Manager metadata now reports version `1.0.7`.
- The TYPO3 documentation release metadata now reports version `1.0.7`.
- Administrators have an explicit 1.0.7 update procedure in the README and
  documentation changelog.

This is a patch release. It does not require a manual data migration or a
configuration change; existing AutoAlt.ai settings, history, and API
connections are retained.

## Release checklist

1. From `packages/alt_text_generator`, run `composer validate --strict --no-check-publish`.
2. Run `composer test` in a development environment with the extension's dev
   dependencies installed.
3. Build or deploy the package with the root TYPO3 project, then run:

   ```bash
   vendor/bin/typo3 extension:setup
   vendor/bin/typo3 cache:warmup
   ```

4. Smoke-test the backend module, API connection, one single-image generation,
   and one Filelist or bulk workflow.
5. Create and publish the `1.0.7` Git tag from the reviewed release commit.

## Customer update steps

```bash
composer update autoaltai/alt-text-generator --with-all-dependencies
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:warmup
```

After updating, customers should confirm their connection and credit balance
under **Media > AutoAlt.ai > Extension Configuration**, then run a single test
generation before processing a larger media library.
