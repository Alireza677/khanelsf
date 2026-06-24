<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">System status / launch checklist</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Review these items before launching or handing over a client website. This page is read-only and does not expose secrets.
            </p>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">System status</h3>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach ($this->systemStatus() as $status)
                    <div class="grid gap-3 p-4 sm:grid-cols-[12rem_8rem_1fr] sm:items-center">
                        <div class="font-medium text-gray-950 dark:text-white">
                            {{ $status['label'] }}
                        </div>

                        <div>
                            @if ($status['ok'])
                                <span class="inline-flex rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20">
                                    OK
                                </span>
                            @else
                                <span class="inline-flex rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20">
                                    Needs attention
                                </span>
                            @endif
                        </div>

                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $status['detail'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Launch checklist</h3>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @foreach ($this->checks() as $check)
                    <div class="grid gap-3 p-4 sm:grid-cols-[12rem_8rem_1fr] sm:items-center">
                        <div class="font-medium text-gray-950 dark:text-white">
                            {{ $check['label'] }}
                        </div>

                        <div>
                            @if ($check['ok'])
                                <span class="inline-flex rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20">
                                    OK
                                </span>
                            @else
                                <span class="inline-flex rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20">
                                    Needs attention
                                </span>
                            @endif
                        </div>

                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $check['detail'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Backup checklist</h3>
            <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                @foreach ($this->backupItems() as $item)
                    <li class="flex gap-2">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"></span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Database export buttons are intentionally not included here. Use server-side backups or trusted hosting backup tools for production restores.
            </p>
        </div>
    </div>
</x-filament-panels::page>
