<x-filament-panels::page>
    <div
        class="space-y-4"
        data-unsaved-entry-count="{{ $unsavedEntryCount }}"
        x-bind:data-unsaved-entry-count="dirtyCount"
        data-unsaved-navigation-message="{{ __('capell-translation-manager::package.unsaved_leave_page') }}"
        x-data="{
            get dirtyCount() {
                return this.$wire.entries.filter(entry => entry.editable &&
                    (entry.targetValue ?? '') !== (this.$wire.originalEntryValues[entry.key] ?? '')
                ).length;
            },
            beforeUnload(event) {
                if (this.dirtyCount > 0) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            },
            confirmNavigation(event) {
                if (this.dirtyCount > 0 && !window.confirm(this.$el.dataset.unsavedNavigationMessage)) {
                    event.preventDefault();
                }
            },
            focusEntry(id) {
                const row = this.$el.querySelector('#' + id);
                if (!row) return;
                row.scrollIntoView({ block: 'center' });
                (row.querySelector('textarea') ?? row).focus({ preventScroll: true });
            }
        }"
        x-on:beforeunload.window="beforeUnload($event)"
        x-on:livewire:navigate.document="confirmNavigation($event)"
        x-on:translation-manager-focus-entry.window="$nextTick(() => focusEntry($event.detail.id))"
        @if ($readinessScanRunId !== null || $missingKeysScanRunId !== null)
            wire:poll.2s="refreshScanResults"
        @endif
    >
        @if ($unsavedEntryCount > 0)
            <x-filament::section compact>
                <div
                    class="flex flex-wrap items-center justify-between gap-3"
                    role="status"
                >
                    <p class="text-sm font-medium text-warning-700 dark:text-warning-300">
                        {{ trans_choice('capell-translation-manager::package.unsaved_entries', $unsavedEntryCount, ['count' => $unsavedEntryCount]) }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('capell-translation-manager::package.unsaved_changes_hint') }}
                    </p>
                </div>
            </x-filament::section>
        @endif

        @if ($pendingNavigation !== null)
            <x-filament::section compact>
                <div
                    class="space-y-3"
                    role="alert"
                >
                    <p class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('capell-translation-manager::package.unsaved_navigation', ['destination' => $pendingNavigation['label']]) }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            wire:click="saveAndContinue"
                            wire:loading.attr="disabled"
                        >
                            {{ __('capell-translation-manager::package.save_changes_and_continue') }}
                        </x-filament::button>
                        <x-filament::button
                            color="gray"
                            wire:click="discardChangesAndContinue"
                            wire:loading.attr="disabled"
                        >
                            {{ __('capell-translation-manager::package.discard_changes') }}
                        </x-filament::button>
                        <x-filament::button
                            color="gray"
                            wire:click="stayOnCurrentFile"
                        >
                            {{ __('capell-translation-manager::package.stay_on_page') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <div class="space-y-5">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="max-w-2xl space-y-1">
                        <h2
                            class="text-base font-semibold text-gray-950 dark:text-white"
                        >
                            {{ __('capell-translation-manager::package.translation_work_queue') }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ __('capell-translation-manager::package.work_queue_description') }}
                        </p>
                    </div>
                    <label class="w-full space-y-1 text-sm lg:max-w-xs">
                        <span
                            class="font-medium"
                            >{{ __('capell-translation-manager::package.translate_into') }}</span
                        >
                        <x-filament::input.wrapper>
                            <x-filament::input.select
                                wire:model.live="targetLocale"
                            >
                                @foreach ($locales as $locale)
                                    <option value="{{ $locale['locale'] }}">
                                        {{ $locale['locale'] }}
                                    </option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                </div>

                @if ($readinessMatrix !== [])
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($readinessMatrix as $readiness)
                            <div
                                @class([
                                'rounded-lg border p-4',
                                'border-success-200 bg-success-50/50 dark:border-success-400/30 dark:bg-success-400/10' => $readiness['ready'],
                                'border-warning-200 bg-warning-50/50 dark:border-warning-400/30 dark:bg-warning-400/10' => ! $readiness['ready'],
                            ])
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <div
                                            class="font-medium text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['locale'] }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-gray-600 dark:text-gray-300"
                                        >
                                            {{ trans_choice('capell-translation-manager::package.readiness_files', $readiness['fileCount'], ['count' => $readiness['fileCount']]) }} · {{ trans_choice('capell-translation-manager::package.readiness_entries', $readiness['entryCount'], ['count' => $readiness['entryCount']]) }}
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-full bg-white/70 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-black/10 dark:text-gray-200"
                                    >
                                        {{ $readiness['ready'] ? __('capell-translation-manager::package.ready') : __('capell-translation-manager::package.needs_work') }}
                                    </span>
                                </div>
                                <dl
                                    class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-3"
                                >
                                    <div>
                                        <dt
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            {{ __('capell-translation-manager::package.queue_missing') }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['missing'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            {{ __('capell-translation-manager::package.queue_stale') }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['stale'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            {{ __('capell-translation-manager::package.queue_changed') }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['changed'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            {{ __('capell-translation-manager::package.queue_extra') }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['extra'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            {{ __('capell-translation-manager::package.queue_fallback') }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['fallback'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            {{ __('capell-translation-manager::package.queue_same') }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-gray-950 dark:text-white"
                                        >
                                            {{ $readiness['same'] }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @elseif ($readinessScanStatus !== null)
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('capell-translation-manager::package.scan_in_progress') }}
                    </p>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button
                        wire:click="continueTranslating"
                        wire:loading.attr="disabled"
                        :disabled="$sourceKey === null || $targetLocale === null"
                    >
                        {{ __('capell-translation-manager::package.continue_translating') }}
                    </x-filament::button>
                    @if ($continueMessage !== null)
                        <p class="text-sm text-success-700 dark:text-success-300" role="status">{{ $continueMessage }}</p>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <details
            class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]"
        >
            <summary
                class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-950 dark:text-white"
            >
                {{ __('capell-translation-manager::package.advanced_filters_and_tools') }}
            </summary>
            <div
                class="space-y-5 border-t border-gray-200 p-4 dark:border-white/10"
            >
                <div>
                    <h3
                        class="text-sm font-medium text-gray-950 dark:text-white"
                    >
                        {{ __('capell-translation-manager::package.source_and_file') }}
                    </h3>
                    <div class="mt-3 grid gap-4 md:grid-cols-3">
                        <label class="space-y-1 text-sm">
                            <span
                                class="font-medium"
                                >{{ __('capell-translation-manager::package.source') }}</span
                            >
                            <x-filament::input.wrapper>
                                <x-filament::input.select
                                    wire:model.live="sourceKey"
                                >
                                    @foreach ($sources as $source)
                                        <option value="{{ $source['key'] }}">
                                            {{ $source['label'] }}
                                        </option>
                                    @endforeach</x-filament::input.select
                            ></x-filament::input.wrapper>
                        </label>
                        <label class="space-y-1 text-sm">
                            <span
                                class="font-medium"
                                >{{ __('capell-translation-manager::package.source_locale') }}</span
                            >
                            <x-filament::input.wrapper>
                                <x-filament::input.select
                                    wire:model.live="sourceLocale"
                                >
                                    @foreach ($locales as $locale)
                                        <option value="{{ $locale['locale'] }}">
                                            {{ $locale['locale'] }}
                                        </option>
                                    @endforeach</x-filament::input.select
                            ></x-filament::input.wrapper>
                        </label>
                        <label class="space-y-1 text-sm">
                            <span
                                class="font-medium"
                                >{{ __('capell-translation-manager::package.file') }}</span
                            >
                            <x-filament::input.wrapper>
                                <x-filament::input.select
                                    wire:model.live="fileKey"
                                >
                                    @foreach ($files as $file)
                                        <option value="{{ $file['key'] }}">
                                            {{ $file['label'] }}
                                        </option>
                                    @endforeach</x-filament::input.select
                            ></x-filament::input.wrapper>
                        </label>
                    </div>
                </div>

                <div>
                    <h3
                        class="text-sm font-medium text-gray-950 dark:text-white"
                    >
                        {{ __('capell-translation-manager::package.entry_filter') }}
                    </h3>
                    <label class="mt-3 block max-w-sm space-y-1 text-sm">
                        <span
                            class="font-medium"
                            >{{ __('capell-translation-manager::package.filter') }}</span
                        >
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="filter">
                                <option value="all">
                                    {{ __('capell-translation-manager::package.filter_all') }}
                                </option>
                                <option value="needs_attention">
                                    {{ __('capell-translation-manager::package.filter_needs_attention') }}
                                </option>
                                <option value="missing">
                                    {{ __('capell-translation-manager::package.filter_missing') }}
                                </option>
                                <option value="changed">
                                    {{ __('capell-translation-manager::package.filter_changed') }}
                                </option>
                                <option value="stale">
                                    {{ __('capell-translation-manager::package.filter_stale') }}
                                </option>
                                <option value="same">
                                    {{ __('capell-translation-manager::package.filter_same') }}
                                </option>
                                <option value="extra">
                                    {{ __('capell-translation-manager::package.filter_extra') }}
                                </option>
                                <option value="fallback">
                                    {{ __('capell-translation-manager::package.filter_fallback') }}
                                </option>
                            </x-filament::input.select></x-filament::input.wrapper
                        >
                    </label>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <h3
                            class="text-sm font-medium text-gray-950 dark:text-white"
                        >
                            {{ __('capell-translation-manager::package.locale_tools') }}
                        </h3>
                        <div class="mt-2 flex flex-col items-start gap-2">
                            <button
                                type="button"
                                wire:click="mountAction('createLocale')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.create_locale') }}
                            </button>
                            <button
                                type="button"
                                wire:click="mountAction('duplicateLocale')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.duplicate_locale') }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-sm font-medium text-gray-950 dark:text-white"
                        >
                            {{ __('capell-translation-manager::package.file_tools') }}
                        </h3>
                        <div class="mt-2 flex flex-col items-start gap-2">
                            <button
                                type="button"
                                wire:click="mountAction('importTranslations')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.import_translations') }}
                            </button>
                            <button
                                type="button"
                                wire:click="mountAction('exportCsv')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.export_csv') }}
                            </button>
                            <button
                                type="button"
                                wire:click="mountAction('exportXliff')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.export_xliff') }}
                            </button>
                            <button
                                type="button"
                                wire:click="mountAction('exportPo')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.export_po') }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-sm font-medium text-gray-950 dark:text-white"
                        >
                            {{ __('capell-translation-manager::package.ai_tools') }}
                        </h3>
                        <div class="mt-2 flex flex-col items-start gap-2">
                            <button
                                type="button"
                                wire:click="mountAction('translateSelected')"
                                @disabled(! $this->aiAvailable())
                                class="text-sm font-medium text-primary-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.translate_selected') }}
                            </button>
                            @if (! $this->aiAvailable())
                                <span
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    >{{ __('capell-translation-manager::package.ai_unavailable') }}</span
                                >
                            @endif
                        </div>
                    </div>
                    <div>
                        <h3
                            class="text-sm font-medium text-gray-950 dark:text-white"
                        >
                            {{ __('capell-translation-manager::package.diagnostics') }}
                        </h3>
                        <div class="mt-2 flex flex-col items-start gap-2">
                            <button
                                type="button"
                                wire:click="mountAction('publishReadiness')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.publish_readiness') }}
                            </button>
                            <button
                                type="button"
                                wire:click="mountAction('scanMissingKeys')"
                                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ __('capell-translation-manager::package.scan_missing_keys') }}
                            </button>
                            @if ($missingKeysScanStatus !== null)
                                <span
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    >{{ __('capell-translation-manager::package.scan_status', ['status' => $missingKeysScanStatus]) }}</span
                                >
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </details>

        @if ($missingCodeKeys !== [])
            <x-filament::section>
                <div class="space-y-3">
                    <h2
                        class="text-base font-semibold text-gray-950 dark:text-white"
                    >
                        {{ __('capell-translation-manager::package.missing_code_keys') }}
                    </h2>
                    <div class="overflow-x-auto">
                        <table
                            class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10"
                        >
                            <thead>
                                <tr
                                    class="text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                >
                                    <th class="py-2 pr-3">
                                        {{ __('capell-translation-manager::package.key') }}
                                    </th>
                                    <th class="py-2 pr-3">
                                        {{ __('capell-translation-manager::package.source_file') }}
                                    </th>
                                    <th class="py-2">
                                        {{ __('capell-translation-manager::package.line') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-white/10"
                            >
                                @foreach ($missingCodeKeys as $missingKey)
                                    <tr>
                                        <td class="py-2 pr-3 align-top">
                                            <code
                                                class="text-xs text-gray-700 dark:text-gray-300"
                                                >{{ $missingKey['key'] }}</code
                                            >
                                        </td>
                                        <td
                                            class="py-2 pr-3 align-top text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ $missingKey['path'] }}
                                        </td>
                                        <td
                                            class="py-2 align-top text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ $missingKey['line'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            @php($filteredEntries = $this->filteredEntries())
            @if ($filteredEntries === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('capell-translation-manager::package.no_attention_entries') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[42rem] table-fixed divide-y divide-gray-200 text-sm dark:divide-white/10"
                    >
                        <thead>
                            <tr
                                class="text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                <th class="w-10 py-2"></th>
                                <th class="w-56 py-2 pr-3">
                                    {{ __('capell-translation-manager::package.key') }}
                                </th>
                                <th class="w-1/3 py-2 pr-3">
                                    {{ __('capell-translation-manager::package.source') }}
                                </th>
                                <th class="w-1/3 py-2">
                                    {{ __('capell-translation-manager::package.target') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-white/10"
                        >
                            @foreach ($filteredEntries as $entry)
                                <tr
                                    id="translation-entry-{{ md5($entry['key']) }}"
                                    wire:key="translation-entry-{{ md5($sourceKey . ':' . $fileKey . ':' . $targetLocale . ':' . $entry['key']) }}"
                                    data-translation-key="{{ $entry['key'] }}"
                                    data-focused-entry="{{ $focusedEntryKey === $entry['key'] ? $entry['key'] : '' }}"
                                    tabindex="-1"
                                    @class(['bg-primary-50 dark:bg-primary-400/10' => $focusedEntryKey === $entry['key']])
                                >
                                    <td class="py-3 align-top">
                                        @if ($entry['editable'])
                                            <x-filament::input.checkbox
                                                value="{{ $entry['key'] }}"
                                                wire:model.live="selectedEntryKeys"
                                            />
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3 align-top">
                                        <code
                                            class="text-xs break-words text-gray-700 dark:text-gray-300"
                                            >{{ $entry['key'] }}</code
                                        ><span
                                            class="mt-1 block text-xs text-gray-500"
                                            >{{ __('capell-translation-manager::package.status_' . $entry['status']) }}</span
                                        >
                                    </td>
                                    <td class="py-3 pr-3 align-top">
                                        <div
                                            class="min-h-10 rounded-md bg-gray-50 p-2 whitespace-pre-wrap text-gray-700 dark:bg-white/5 dark:text-gray-300"
                                        >
                                            {{ $entry['sourceValue'] }}
                                        </div>
                                    </td>
                                    <td class="py-3 align-top">
                                        @if ($entry['editable'])
                                            <x-filament::input.wrapper>
                                                <textarea
                                                    wire:model.defer="entries.{{ $entry['index'] }}.targetValue"
                                                    rows="3"
                                                    class="w-full border-0 bg-transparent text-sm outline-none focus:ring-0"
                                                ></textarea>
                                            </x-filament::input.wrapper>
                                            @if (array_key_exists($entry['key'], $pendingAiSuggestions))
                                                <div
                                                    class="border-primary-200 bg-primary-50 dark:border-primary-400/30 dark:bg-primary-400/10 mt-2 rounded-md border p-2 text-xs"
                                                >
                                                    <div
                                                        class="text-primary-800 dark:text-primary-200 font-medium"
                                                    >
                                                        {{ __('capell-translation-manager::package.ai_suggestion') }}
                                                    </div>
                                                    <div
                                                        class="mt-1 whitespace-pre-wrap text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ $pendingAiSuggestions[$entry['key']] }}
                                                    </div>
                                                    <div
                                                        class="mt-2 flex gap-2"
                                                    >
                                                        <button
                                                            type="button"
                                                            wire:click="acceptAiSuggestion(@js($entry['key']))"
                                                            class="text-primary-700 dark:text-primary-300 text-xs font-medium"
                                                        >
                                                            {{ __('capell-translation-manager::package.accept_suggestion') }}</button
                                                        ><button
                                                            type="button"
                                                            wire:click="rejectAiSuggestion(@js($entry['key']))"
                                                            class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                                        >
                                                            {{ __('capell-translation-manager::package.reject_suggestion') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div
                                                class="min-h-10 rounded-md bg-gray-50 p-2 whitespace-pre-wrap text-gray-500 dark:bg-white/5"
                                            >
                                                {{ $entry['targetValue'] }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
