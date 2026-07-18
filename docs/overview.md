# Translation Manager

<!-- prettier-ignore-start -->

## What it does for you

Translation Manager keeps the application's and installed packages' file-based translations ready for another language. Create or duplicate a locale, compare it with the source language, edit target values, and check coverage before launch.

## Your screens

- **Locales**: the languages available for the selected translation source, including whether source and override files exist.
- **The translation editor**: where you compare source and target values, edit target values, and filter entries by their status.
- **Publish readiness**: the per-locale count of missing, stale, changed, fallback, and other translation states.

## What you can do

- Create a locale from the configured source locale, or duplicate an existing locale.
- Edit the target translation next to the source value. Application translations are saved in the application's language directory; package translations normally go to Laravel's `lang/vendor/<namespace>` override directory rather than changing vendor files.
- Filter for entries that need attention: missing, stale, or changed since the source was last translated.
- Check locale publish readiness before launch.
- Import or export the current file as CSV, XLIFF, or PO/Gettext.
- Scan configured application, view, and route paths for literal translation keys that are missing from the selected source.
- Generate suggestions for selected entries from exact translation-memory matches and the configured AI translator.

## Where to find it

Go to **System > Translation Manager** in the admin. Access requires the same `Manage:ExtensionsPage` permission used to manage extensions.

## Before you make changes

- Translation Manager writes PHP and JSON language files directly. The application language directory and package override directory must be writable and must persist across deployments.
- There is no translation database, version history, automatic backup, or rollback. Keep the language files in version control or take a backup before a large import, locale creation, or duplication.
- Creating a locale writes blank values for every source string. Duplicating a locale copies every translation file from the chosen locale. These multi-file operations are synchronous and are not transactional, so restore from your backup if a write stops part-way through.
- The configured source locale defaults to `en`. Integrity validation always uses that configured source locale, even if you select a different source locale in the editor, so keep the selection aligned when saving or importing translations.

## Review and publish workflow

1. Select the application, package, or vendor source, then choose the source locale, target locale, and translation file.
2. Filter the entries and edit only string values. Nested PHP arrays are shown as flattened keys; non-string values are read-only.
3. Save the file, then review **Publish readiness** for the whole locale rather than only the open file.
4. Commit or deploy the translation file together with its `.capell-meta.json` sidecar. The sidecar records the source hash for each saved key and allows precise stale checks.

If no sidecar exists, Translation Manager falls back to comparing file modification times. A newer source file can therefore mark every differing target entry as stale until the target is saved with current metadata.

## Import, export, and validation

- Export operates on the currently selected file and locale. Start from that export when handing translations to another person or system.
- Import is pasted into the admin form; it is not a file-upload workflow. CSV requires `key` and `target_value` columns. XLIFF and PO imports expect the key conventions used by the corresponding exports.
- Imported keys are merged into the selected target file. The importer does not restrict them to keys in the selected source file, so a wrong-file or foreign key can be added as an **Extra** entry. Check the imported and skipped counts, then review the file and readiness result.
- Before any save or import is written, non-empty translations must retain source placeholders such as `:name`, the source number of plural separators, and any configured glossary term. A validation failure prevents that write.

## Translation suggestions

**Translate selected** appears only when the host application has bound an available translation service. Installing the optional AI Orchestrator integration alone does not provide a translator. The action first reuses exact source-text matches from existing translations, then sends remaining selected source strings to the configured translator.

Suggestions are drafts. Accepting one only places it in the editor; press **Save** to write it. Translation Manager does not keep its own AI history, but the configured translator receives the selected source text, so check that service's privacy and retention terms before using it for sensitive copy.

## Good to know

- **Missing** means the target value has not been translated. **Stale** means its source value changed after the target was saved; both prevent the locale from being publish-ready.
- **Changed** entries differ from the source but have no stale-source warning; they do not, on their own, make the locale unready. Fallback values and extra keys are reported so you can decide whether to tidy them.
- Run **Publish readiness** before launching a locale. It totals each status across every editable entry in the selected source.
- **Scan missing keys** recognises literal keys in `__()`, `trans()`, `trans_choice()`, and `@lang()` calls in the configured scan paths. It deliberately cannot discover dynamically assembled translation keys.
- A missing target can be covered by Laravel's fallback locale and is then reported as **Fallback**, which does not block readiness.
- If a save or import fails, check the application log and the target directory's permissions. Restore the affected files from version control or your backup; the package does not retain a recovery copy.

---

For how to use Translation Manager, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
