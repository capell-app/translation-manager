# Translation Manager

<!-- prettier-ignore-start -->

## What it does for you

Translation Manager keeps file-based Capell translations ready for another language. Create or duplicate a locale, compare it with the configured source locale, edit the safe override values, and check coverage before launch.

## Your screens

- **Locales**: the languages available for the selected translation source, including whether source and override files exist.
- **The translation editor**: where you compare source and target values, edit target values, and filter entries by their status.
- **Publish readiness**: the per-locale count of missing, stale, changed, fallback, and other translation states.

## What you can do

- Create a locale from the configured source locale, or duplicate an existing locale.
- Edit the target translation next to the source value and save it to the package's safe override location.
- Filter for entries that need attention: missing, stale, or changed since the source was last translated.
- Check locale publish readiness before launch.
- Import or export the current file as CSV, XLIFF, or PO/Gettext.
- Generate suggestions for selected entries from exact translation-memory matches, with optional AI drafting when it is configured.

## Where to find it

Go to **Translation Manager** in the admin to add locales and edit translations.

## Good to know

- **Missing** means the target value has not been translated. **Stale** means its source value changed after the target was saved; both prevent the locale from being publish-ready.
- **Changed** entries differ from the source but have no stale-source warning; they do not, on their own, make the locale unready. Fallback values and extra keys are reported so you can decide whether to tidy them.
- Run **Publish readiness** before launching a locale. It totals each status across every editable entry in the selected source.
- CSV, XLIFF, and PO imports update only target values for the current file and locale. Export first when handing work to an external translator.
- Translation memory reuses an existing target value only when its source value is an exact match. AI suggestions are drafts: review them before applying them to the target values.

---

For how to use Translation Manager, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
