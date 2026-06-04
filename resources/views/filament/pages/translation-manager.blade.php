<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <div class="grid gap-4 md:grid-cols-5">
                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.source') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="sourceKey">
                            @foreach ($sources as $source)
                                <option value="{{ $source['key'] }}">
                                    {{ $source['label'] }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.source_locale') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="sourceLocale">
                            @foreach ($locales as $locale)
                                <option value="{{ $locale['locale'] }}">
                                    {{ $locale['locale'] }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.target_locale') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="targetLocale">
                            @foreach ($locales as $locale)
                                <option value="{{ $locale['locale'] }}">
                                    {{ $locale['locale'] }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.file') }}
                    </span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="fileKey">
                            @foreach ($files as $file)
                                <option value="{{ $file['key'] }}">
                                    {{ $file['label'] }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.filter') }}
                    </span>
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
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        </x-filament::section>

        @if ($readinessMatrix !== [])
            <x-filament::section>
                <div class="space-y-3">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('capell-translation-manager::package.publish_readiness') }}
                    </h2>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($readinessMatrix as $readiness)
                            <div
                                class="rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-950 dark:text-white">
                                            {{ $readiness['locale'] }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ trans_choice('capell-translation-manager::package.readiness_files', $readiness['fileCount'], ['count' => $readiness['fileCount']]) }}
                                            ·
                                            {{ trans_choice('capell-translation-manager::package.readiness_entries', $readiness['entryCount'], ['count' => $readiness['entryCount']]) }}
                                        </div>
                                    </div>

                                    <span
                                        @class([
                                            'rounded-full px-2 py-1 text-xs font-medium',
                                            'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300' => $readiness['ready'],
                                            'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-300' => ! $readiness['ready'],
                                        ])
                                    >
                                        {{ $readiness['ready'] ? __('capell-translation-manager::package.ready') : __('capell-translation-manager::package.needs_work') }}
                                    </span>
                                </div>

                                <dl class="mt-3 grid grid-cols-5 gap-2 text-xs">
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">
                                            {{ __('capell-translation-manager::package.status_missing') }}
                                        </dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">
                                            {{ $readiness['missing'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">
                                            {{ __('capell-translation-manager::package.status_stale') }}
                                        </dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">
                                            {{ $readiness['stale'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">
                                            {{ __('capell-translation-manager::package.status_changed') }}
                                        </dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">
                                            {{ $readiness['changed'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">
                                            {{ __('capell-translation-manager::package.status_extra') }}
                                        </dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">
                                            {{ $readiness['extra'] }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">
                                            {{ __('capell-translation-manager::package.status_fallback') }}
                                        </dt>
                                        <dd class="font-medium text-gray-950 dark:text-white">
                                            {{ $readiness['fallback'] }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>
        @endif

        @if ($missingCodeKeys !== [])
            <x-filament::section>
                <div class="space-y-3">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('capell-translation-manager::package.missing_code_keys') }}
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
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
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($missingCodeKeys as $missingKey)
                                    <tr>
                                        <td class="py-2 pr-3 align-top">
                                            <code class="text-xs text-gray-700 dark:text-gray-300">
                                                {{ $missingKey['key'] }}
                                            </code>
                                        </td>
                                        <td class="py-2 pr-3 align-top text-xs text-gray-500 dark:text-gray-400">
                                            {{ $missingKey['path'] }}
                                        </td>
                                        <td class="py-2 align-top text-xs text-gray-500 dark:text-gray-400">
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
            @if ($this->filteredEntries() === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('capell-translation-manager::package.no_entries') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table
                        class="w-full table-fixed divide-y divide-gray-200 text-sm dark:divide-white/10"
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
                            @foreach ($this->filteredEntries() as $entry)
                                <tr>
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
                                        >
                                            {{ $entry['key'] }}
                                        </code>
                                        <span
                                            class="mt-1 block text-xs text-gray-500"
                                        >
                                            {{ __('capell-translation-manager::package.status_' . $entry['status']) }}
                                        </span>
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
                                                    class="mt-2 rounded-md border border-primary-200 bg-primary-50 p-2 text-xs dark:border-primary-400/30 dark:bg-primary-400/10"
                                                >
                                                    <div class="font-medium text-primary-800 dark:text-primary-200">
                                                        {{ __('capell-translation-manager::package.ai_suggestion') }}
                                                    </div>
                                                    <div class="mt-1 whitespace-pre-wrap text-gray-700 dark:text-gray-300">
                                                        {{ $pendingAiSuggestions[$entry['key']] }}
                                                    </div>
                                                    <div class="mt-2 flex gap-2">
                                                        <button
                                                            type="button"
                                                            wire:click="acceptAiSuggestion(@js($entry['key']))"
                                                            class="text-primary-700 text-xs font-medium dark:text-primary-300"
                                                        >
                                                            {{ __('capell-translation-manager::package.accept_suggestion') }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            wire:click="rejectAiSuggestion(@js($entry['key']))"
                                                            class="text-gray-600 text-xs font-medium dark:text-gray-300"
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
