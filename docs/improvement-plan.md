# Translation Manager — Improvement & Growth Plan
> Package: capell-app/translation-manager · Kind: package · Tier: premium · Product group: Capell Admin · Bundle: admin · Status: Draft

## 1. Snapshot

Translation Manager is an admin-only Filament package (single surface: `admin`) that turns Laravel language files into an editable source-vs-target comparison grid at `/admin/translation-manager`. All domain behaviour lives in 13 thin Actions (`src/Actions`) that delegate to two pluggable contracts — `TranslationSourceResolver` (default `ConfigTranslationSourceResolver`, discovers app + Composer-package + vendor `resources/lang` dirs) and `TranslationFileStore` (default `FileTranslationFileStore`, reads/writes PHP and JSON lang files with override-path safety) — plus an optional `TranslationAITranslator` (default `NullTranslationAITranslator`). It owns no migrations, settings, or DB tables (`database.migrations: false`), and produces no frontend output. Third-party deps are minimal: `lorisleiva/laravel-actions`, `spatie/laravel-data`, `spatie/laravel-package-tools`. Eight Data classes (`src/Data`) carry every boundary payload — no anonymous arrays leak between layers.

The `supports` / soft-dependency pattern is implemented correctly and is worth highlighting. `composer.json` lists `capell-app/ai-orchestrator` under `suggest` (not `require`); `capell.json` lists it under `dependencies.supports`. `TranslationManagerServiceProvider::registerOptionalAIOrchestratorModule()` guards with `interface_exists(AIOrchestratorModule::class)` and `class_exists(AIOrchestratorModuleRegistry::class)` and only registers inside an `afterResolving(AIOrchestratorModuleRegistry::class, …)` callback, so the AIOrchestrator-typed classes in `src/Integrations/AI/` are never autoloaded when the orchestrator is absent. This is the clean inversion of a hard dependency: the package runs standalone with a Null translator and lights up AI drafting only when the orchestrator is installed and binds a real `TranslationAITranslator`.

Current marketplace `summary` (verbatim): *"Translation Manager provides a file-based Filament editor for Laravel language files with translation workflow actions, stale detection, CSV and XLIFF import-export, per-locale publish readiness, AI drafting, and safe package override writes."* Screenshot count: **1** image is shipped (`docs/assets/marketplace/extension-card.jpg`), but `docs/screenshots.json` declares a **5-entry** contract (empty state, comparison grid, create-locale modal, duplicate-locale modal, AI translate-selected). Mismatch: the four required captures and the optional AI capture are specified but not present in the repo.

## 2. Improvements (existing functionality)

- **Surface CSV/XLIFF import-export and publish-readiness in the UI** — `ExportTranslationEntriesToCsvAction`, `ExportTranslationEntriesToXliffAction`, `ImportTranslationEntriesFromCsvAction`, `ImportTranslationEntriesFromXliffAction`, and `BuildLocalePublishReadinessAction` are fully implemented and tested, but a repo-wide search finds **no caller outside the package** — they are reachable only via `Action::run()` or programmatic API, never from `TranslationManagerPage::getHeaderActions()`. Four advertised capabilities (`translation.files.csv-import-export`, `translation.files.xliff-import-export`, `translation.files.publish-readiness`, and the stale view) have no admin entry point. Add header actions: Export (CSV/XLIFF download), Import (file upload), and a publish-readiness summary badge/modal. — `src/Filament/Pages/TranslationManagerPage.php` — M

- **Add the missing-status filter to the grid filter set** — `FileTranslationFileStore::status()` emits `missing|stale|changed|same|extra`, and the blade filter offers all five, but there is no "needs attention" composite (missing + stale + changed) which is the translator's primary working view. Add a combined filter option backed by `filteredEntries()`. — `src/Filament/Pages/TranslationManagerPage.php`, `resources/views/filament/pages/translation-manager.blade.php` — S

- **Replace the raw `<select>`/`<textarea>` grid with Filament components** — the page uses hand-rolled Tailwind `<select>` and `<textarea>` controls and a manual `array_search` index lookup in blade (`$entryIndex = array_search($entry['key'], array_column($entries, 'key'), true)`). This is O(n) per row and bypasses Filament theming/validation. Move to a Filament table or schema-driven repeater for consistent styling, keyboard nav, and free dark-mode handling. — `resources/views/filament/pages/translation-manager.blade.php` — L

- **Persist the user's selected source/locale/file between visits** — `mount()` always resets to `sources[0]` and the first non-source locale; a translator returning to the page loses their place. Store last selection in session or user preferences. — `src/Filament/Pages/TranslationManagerPage.php` (`mount`, `refreshLocales`) — S

- **Make the AIOrchestrator module registration idempotent / typed** — `registerOptionalAIOrchestratorModule()` calls `method_exists($registry, 'register')` defensively even though the registry type is known when the class exists. Once ai-orchestrator is installed, the registry contract is available; call `$registry->register(...)` against the typed contract and drop the `method_exists` guard to fail loudly on contract drift. — `src/Providers/TranslationManagerServiceProvider.php` — S

- **Stream large CSV/XLIFF exports instead of building in memory** — `exportCsv()` uses `php://temp` (fine) but `ExportTranslationEntriesToXliffAction` builds a full `DOMDocument` and returns a string; for large lang sets this holds the entire document in memory. Acceptable now (admin, small files) but document the assumption or chunk for very large files. — `src/Actions/ExportTranslationEntriesToXliffAction.php` — S

## 3. Missing Features (gaps)

Tie-back to `capabilities[]` (8 declared) and standard translation-workflow norms:

- **Inline missing-key detection / app scanning (differentiator)** — the package only compares files that already exist on disk; it does not scan Blade/PHP for `__()`/`@lang()` calls to surface keys referenced in code but absent from lang files. This is the single highest-value translation-tooling feature and is not represented in `capabilities[]`. — gap vs norm: missing-key detection.

- **Translation memory / glossary (differentiator)** — no reuse of previously translated strings or enforced term consistency. AI drafting (`TranslateSelectedEntriesAction`) translates each selected key independently with no memory. — gap vs norm: TM/glossary.

- **Machine-translation review/approval workflow** — AI suggestions are written straight into `entries[*].targetValue` in page state (`translateSelectedEntries()`); there is no per-suggestion accept/reject, no diff, no provenance flag distinguishing AI drafts from human edits before save. The AIOrchestrator capability is correctly declared at `ApprovalLevel::Draft`, but the page itself has no review gate. — gap vs norm: MT-with-review.

- **PO / gettext import-export** — CSV and XLIFF 1.2 are covered; `.po`/`.pot` (common for translator hand-off and Weblate/Crowdin/Poedit interop) is not. — gap vs norm: import/export formats.

- **Per-locale completeness dashboard** — `BuildLocalePublishReadinessAction` computes status counts and a `ready` flag per locale, but there is no aggregate view across all locales/files (a coverage matrix: locale × file × % complete). The data object exists; the surface does not. — gap vs norm: per-locale completeness.

- **Fallback-chain awareness** — comparison and status logic treat source vs target as a flat pair. Laravel's `fallback_locale` chain is ignored: a key resolved via fallback is reported as `missing` even though the app renders a value. Surfacing "covered by fallback" would prevent false-negative gaps. — gap vs norm: fallback chains.

- **Pluralization / parameter validation** — no check that target strings preserve `:placeholders` and pipe-delimited plural forms present in the source. A translator can silently drop `:count` and break rendering. High-value, low-cost guardrail. — gap vs norm/table-stakes.

- **Partial-override merge** — see Issues §4; today an override file fully shadows the source file rather than merging, which is a feature gap (no per-key override) as much as a correctness risk.

Table-stakes already shipped: source/target grid, stale detection (mtime-based), CSV + XLIFF import-export, locale create/duplicate, safe override-path writes, optional AI drafting.

## 4. Issues / Risks

- **Override files fully shadow source files (partial-override data risk)** — `FileTranslationFileStore::basePath(forWrite:false)` returns *either* the override path *or* the source path for a given file, never a merge (`src/Support/FileTranslationFileStore.php:367-386`). `read()` therefore loads only one file. If an override `lang/vendor/<ns>/<locale>/<file>.php` exists but contains a subset of keys, the un-overridden source keys vanish from both the comparison grid and from `createLocale`/`duplicateLocale` snapshots taken from that locale. Laravel's runtime *merges* package translations with overrides, so the editor's view diverges from what the app actually renders. — `src/Support/FileTranslationFileStore.php` — correctness.

- **Stale detection is file-mtime based, not key-level** — `status()` marks a target key `stale` when the source file's mtime is newer than the target file's mtime (`FileTranslationFileStore.php:451`). Editing one unrelated key in the source file marks *every* target key in that file stale, producing noisy false positives. A per-key change hash (or last-translated timestamp) would be more accurate. — `src/Support/FileTranslationFileStore.php` — tech debt / UX.

- **Health check asserts API version only** — `TranslationManagerHealthCheck` implements just `compatibleCapellApiVersion(): '^4.0'` (`src/Health/TranslationManagerHealthCheck.php`), yet the `capell.json` healthChecks label claims it proves "package surfaces, providers, and install health are discoverable by Diagnostics." This is the standard shape shared verbatim by sibling packages (insights, comments, etc.), so it is **not a package-specific stub**, but the manifest label overstates what the class verifies. Either tighten the label to "API-version compatibility" or add real assertions (page registered, contracts bound, override path writable). — `src/Health/TranslationManagerHealthCheck.php`, `capell.json:91-99` — manifest/label mismatch.

- **No authorization on file writes beyond page access** — `canAccess()` gates on `ExtensionsPage::canManageExtensions()`, but the package declares `permissions: []` and there is no finer-grained gate for *writing* package/vendor overrides vs editing app strings. Anyone who can open the page can write to `lang/vendor/*`. `package_source_writes` defaults to `false` (good), but a per-source write permission would harden this. — `src/Filament/Pages/TranslationManagerPage.php`, `capell.json` permissions — security.

- **Performance budget unverified** — `capell.json` sets `performance.adminQueryBudget: 40`, but the page issues **zero DB queries** (it is filesystem-only). The budget is irrelevant as written; meanwhile the real cost is unbudgeted filesystem I/O: `ListInstalledLocalesAction` → `locales()` calls `files()` (full `allFiles()` scan) once per locale, and `BuildLocalePublishReadinessAction` runs a full `comparison()` (read+flatten both locales) per file. On a host app with many packages/locales this is O(sources × locales × files). Add a filesystem-scan budget or cache `InstalledVersions` source discovery. — `src/Support/FileTranslationFileStore.php:24-90`, `capell.json:79-90` — performance budget.

- **`ConfigTranslationSourceResolver` scans every Composer package on each `sources()` call** — `composerPackageSources()` iterates `InstalledVersions::getInstalledPackages()`, opens each `composer.json`, and reflects on its service provider's static `$name` (`src/Support/ConfigTranslationSourceResolver.php:98-178`). The resolver is a singleton but `sources()` recomputes on every invocation, and `TranslationManagerPage` calls it on mount plus on every source change. Memoize within the request. — `src/Support/ConfigTranslationSourceResolver.php` — performance.

- **Public-output safety: N/A but worth a guard test** — the package has `surfaces: ["admin"]` and emits no frontend HTML, so the Capell public-output rules do not apply. No risk found; no action needed beyond noting it in the manifest (already `frontend: []`, `cacheable: false`).

- **i18n of the package itself** — only `resources/lang/en/package.php` ships. A translation-management tool shipping English-only strings is an avoidable optics gap. — `resources/lang/en/package.php` — i18n.

- **Test gaps** — coverage is genuinely strong (38 `it()` cases spanning Actions, file store, page Livewire state, AI module, manifest, and Data boundaries, incl. path-traversal rejection and override-write defaults). Untested: the partial-override merge scenario above; concurrent-write / last-write-wins on `write()`; placeholder/plural preservation (no such feature yet); very large file export memory. — `tests/` — test debt.

## 5. Marketplace & Selling

**Critique.** The current `summary` and composer `description` diverge. `capell.json` summary is a 40-word feature dump (editor, workflow actions, stale detection, CSV+XLIFF, publish readiness, AI drafting, override writes) — comprehensive but unreadable as a one-liner, and it advertises CSV/XLIFF/publish-readiness that have no UI entry point (see §2), risking a "where is it?" support load. The composer `description` ("File-based Laravel translation management for Capell and Filament admin panels.") is accurate but generic and undersells the differentiators (override safety, AI drafting, no-DB footprint).

**Improved one-sentence summary:** *Edit, translate, and ship your Capell language files from one Filament screen — with side-by-side source/target comparison, stale-key detection, and AI drafting that never touches a database.*

**Improved 3–4 sentence description:** *Translation Manager gives admins a file-based editor for every Laravel language file in your Capell app and its packages, without migrations or new tables. Compare source and target locales side by side, spot missing and stale keys at a glance, and create or duplicate locales in one click. Edits to package and vendor strings are written safely to Laravel's override paths, so upgrades never clobber your translations. Install AI Orchestrator to add one-click AI drafting for selected keys, with review before anything is saved.*

**Screenshot/media gaps.** Only 1 of the 5 declared captures (`docs/screenshots.json`) is shipped. Produce the four required PNGs (empty state, comparison grid, create-locale modal, duplicate-locale modal) and the optional AI capture. The comparison grid is the money shot — capture it with a partial `fr` locale so missing/changed statuses are visible. Add a short GIF of the create→translate→save loop for the listing.

**Pricing / tier / bundle.** `tier: premium`, `bundle: admin`, `proposedLicense: paid`, `requestedCertification: first-party`, `supportPolicy: priority` — appropriate for a developer/agency tool. Position inside the **admin bundle** as the localisation utility. The no-DB, override-safe footprint is a genuine premium justification (low risk to install).

**Cross-sell.** Hard requires `capell-app/admin` + `capell-app/core`. Soft `supports capell-app/ai-orchestrator` — make this the headline upsell: "Add AI Orchestrator for AI translation drafting." Bundle into an **Extension Suite** alongside **seo-suite** (translated SEO metadata per locale) and **ai-orchestrator** (MT). Cross-link the README's "Best Used With" (currently Welcome Tour / Diagnostics / Foundation Theme) to add ai-orchestrator and seo-suite, which are far more relevant to the localisation buyer.

**Differentiators / value props / target buyer.** Differentiators: upgrade-safe override writes; zero database footprint; clean optional-AI integration via `supports`; XLIFF interop. Target buyer: agencies and developers running multi-locale Capell sites who need to localise package/vendor strings without forking vendor files. Value prop: "localise your whole app — including third-party packages — without migrations and without losing edits on upgrade."

**Keywords/tags (8–12):** `translation`, `localization`, `i18n`, `l10n`, `language-files`, `xliff`, `csv-import`, `filament`, `multilingual`, `ai-translation`, `vendor-overrides`, `capell-admin`.

## 6. Prioritized Roadmap

| Item | Bucket | Effort | Impact | Section ref |
| --- | --- | --- | --- | --- |
| Wire CSV/XLIFF import-export + publish-readiness into page header actions | Now | M | High | §2, §3 |
| Ship the 4 required + 1 optional screenshots (fix screenshots.json mismatch) | Now | S | High | §1, §5 |
| Fix partial-override merge so editor view matches Laravel runtime | Now | M | High | §4 |
| Correct manifest healthChecks label / adminQueryBudget to match reality | Now | S | Med | §4 |
| Memoize source discovery + locale/comparison filesystem scans | Now | S | Med | §4 |
| Rewrite summary + composer description; add ai-orchestrator/seo-suite cross-sell | Now | S | Med | §5 |
| Add placeholder/plural preservation validation on save | Next | M | High | §3 |
| Add MT review gate (accept/reject AI drafts before write) | Next | M | High | §3 |
| Per-locale completeness/coverage matrix view (uses existing readiness data) | Next | M | Med | §3 |
| Key-level stale detection (replace file-mtime heuristic) | Next | M | Med | §4 |
| Persist last source/locale/file selection per user | Next | S | Med | §2 |
| Fallback-chain awareness ("covered by fallback" status) | Later | M | Med | §3 |
| Missing-key detection via code scanning of `__()`/`@lang()` | Later | L | High | §3 |
| Translation memory / glossary for consistent reuse | Later | L | Med | §3 |
| PO/gettext import-export for Crowdin/Weblate/Poedit interop | Later | M | Med | §3 |
| Replace raw blade grid with Filament table/repeater components | Later | L | Med | §2 |
