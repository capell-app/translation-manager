<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <div class="grid gap-4 md:grid-cols-5">
                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.source') }}
                    </span>
                    <select
                        wire:model.live="sourceKey"
                        class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        @foreach ($sources as $source)
                            <option value="{{ $source['key'] }}">
                                {{ $source['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.source_locale') }}
                    </span>
                    <select
                        wire:model.live="sourceLocale"
                        class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        @foreach ($locales as $locale)
                            <option value="{{ $locale['locale'] }}">
                                {{ $locale['locale'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.target_locale') }}
                    </span>
                    <select
                        wire:model.live="targetLocale"
                        class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        @foreach ($locales as $locale)
                            <option value="{{ $locale['locale'] }}">
                                {{ $locale['locale'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.file') }}
                    </span>
                    <select
                        wire:model.live="fileKey"
                        class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        @foreach ($files as $file)
                            <option value="{{ $file['key'] }}">
                                {{ $file['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">
                        {{ __('capell-translation-manager::package.filter') }}
                    </span>
                    <select
                        wire:model.live="filter"
                        class="w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        <option value="all">
                            {{ __('capell-translation-manager::package.filter_all') }}
                        </option>
                        <option value="missing">
                            {{ __('capell-translation-manager::package.filter_missing') }}
                        </option>
                        <option value="changed">
                            {{ __('capell-translation-manager::package.filter_changed') }}
                        </option>
                        <option value="same">
                            {{ __('capell-translation-manager::package.filter_same') }}
                        </option>
                        <option value="extra">
                            {{ __('capell-translation-manager::package.filter_extra') }}
                        </option>
                    </select>
                </label>
            </div>
        </x-filament::section>

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
                                class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
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
                                @php
                                    $entryIndex = array_search($entry['key'], array_column($entries, 'key'), true);
                                @endphp

                                <tr>
                                    <td class="py-3 align-top">
                                        @if ($entry['editable'])
                                            <input
                                                type="checkbox"
                                                value="{{ $entry['key'] }}"
                                                wire:model.live="selectedEntryKeys"
                                                class="text-primary-600 rounded border-gray-300 shadow-sm"
                                            />
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3 align-top">
                                        <code
                                            class="break-words text-xs text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $entry['key'] }}
                                        </code>
                                        <span
                                            class="mt-1 block text-xs text-gray-500"
                                        >
                                            {{ $entry['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-3 align-top">
                                        <div
                                            class="min-h-10 whitespace-pre-wrap rounded-md bg-gray-50 p-2 text-gray-700 dark:bg-white/5 dark:text-gray-300"
                                        >
                                            {{ $entry['sourceValue'] }}
                                        </div>
                                    </td>
                                    <td class="py-3 align-top">
                                        @if ($entry['editable'] && $entryIndex !== false)
                                            <textarea
                                                wire:model.defer="entries.{{ $entryIndex }}.targetValue"
                                                rows="3"
                                                class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900"
                                            ></textarea>
                                        @else
                                            <div
                                                class="min-h-10 whitespace-pre-wrap rounded-md bg-gray-50 p-2 text-gray-500 dark:bg-white/5"
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
