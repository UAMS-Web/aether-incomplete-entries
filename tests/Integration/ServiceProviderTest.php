<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Addon;
use UAMSWeb\Aether\IncompleteEntries\Widgets\IncompletePublishedEntries;

/**
 * The add-on boots inside a minimal Statamic.
 *
 * @command  vendor/bin/pest tests/Integration/ServiceProviderTest.php
 */
it('is discovered by Statamic as an add-on', function (): void {
    // Expect the add-on among those Statamic booted
    expect(Addon::all()->map->package()->all())->toContain('uams-web/aether-incomplete-entries');
});

it('registers the dashboard widget under its handle', function (): void {
    // Expect Statamic to resolve the widget by the handle the site used
    expect(app('statamic.widgets')->has('incomplete_published_entries'))->toBeTrue()
        ->and(app('statamic.widgets')->get('incomplete_published_entries'))->toBe(IncompletePublishedEntries::class);
});

it('registers the scan command under its signature', function (): void {
    // Expect Artisan to know the command by the signature the site used
    expect(Artisan::all())->toHaveKey('cp:scan-incomplete-published-entries');
});

it('listens for a saved entry to record a content change', function (): void {
    // Expect Statamic's EntrySaved event to reach the add-on's listener
    expect(Event::hasListeners(EntrySaved::class))->toBeTrue();
});

it('schedules the scan hourly by default', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(static fn (ScheduleEvent $event): bool => str_contains((string) $event->command, 'cp:scan-incomplete-published-entries'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('0 * * * *');
});
