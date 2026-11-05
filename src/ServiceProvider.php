<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\IncompleteEntries;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Statamic\Events\EntrySaved;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Widgets\Widget;
use UAMSWeb\Aether\IncompleteEntries\Console\ScanIncompletePublishedEntries;
use UAMSWeb\Aether\IncompleteEntries\Listeners\RecordIncompletePublishedEntriesContentChange;
use UAMSWeb\Aether\IncompleteEntries\Widgets\IncompletePublishedEntries;

/**
 * Service provider for the add-on.
 *
 * The host application discovers it through `extra.laravel.providers` in
 * `composer.json`. Register the add-on's tags, fieldtypes, listeners, routes and
 * the like here, using the properties `AddonServiceProvider` documents, or in
 * `bootAddon()` for anything that needs Statamic booted first.
 */
class ServiceProvider extends AddonServiceProvider
{
    /**
     * The view namespace, so the widget's view resolves as
     * `aether-incomplete-entries::widgets.incomplete_published_entries` and a site can
     * override it from `resources/views/vendor/aether-incomplete-entries`.
     *
     * @var string
     */
    protected $viewNamespace = 'aether-incomplete-entries';

    /**
     * The dashboard widget, under the handle `incomplete_published_entries`.
     *
     * @var list<class-string<Widget>>
     */
    protected $widgets = [
        IncompletePublishedEntries::class,
    ];

    /**
     * The scan command, `cp:scan-incomplete-published-entries`.
     *
     * @var list<class-string<Command>>
     */
    protected $commands = [
        ScanIncompletePublishedEntries::class,
    ];

    /**
     * Saving an entry records that content changed since the last scan.
     *
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        EntrySaved::class => [
            RecordIncompletePublishedEntriesContentChange::class,
        ],
    ];

    /**
     * The Vite build of the Control Panel assets. `input` and `publicDirectory`
     * must match `vite.config.js`; the built files are committed under
     * `resources/dist`.
     *
     * @var array{input: list<string>, publicDirectory: string}
     */
    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    /**
     * Run the scan out of band, so the dashboard widget reads its result instead of
     * scanning inside a request (UAMS-Web/uams-statamic-archive#2506). The scan takes
     * over a minute, so it runs in the background and never overlaps itself. Hourly,
     * because the widget keeps the last result through entry saves and only needs to
     * catch up with them. The `schedule.enabled` config key turns this off for a site
     * that schedules the command itself.
     */
    #[\Override]
    protected function schedule(Schedule $schedule): void
    {
        // Leave scheduling to the application when the config says so. Statamic calls this
        // before it merges the add-on's config file, so the default is passed here: a site's
        // own copy of the file is already loaded, and the key is absent otherwise.
        if (config('aether-incomplete-entries.schedule.enabled', true) === false) {
            return;
        }

        // Register the hourly background scan
        $schedule->command('cp:scan-incomplete-published-entries')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    }
}
