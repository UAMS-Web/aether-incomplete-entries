<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use UAMSWeb\Aether\IncompleteEntries\Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;

/**
 * Pins what the published-entries scan asks Statamic's entry query for
 * (UAMS-Web/aether-incomplete-entries#83), against real entries in the isolated Stache.
 *
 * The scan's three query behaviors are the status filter, the id order, and the lookup by an
 * id list. `statamic/cms` 6.42.0 reworks how the Stache sorts and how `whereIn` treats an empty
 * list (statamic/cms#15877, statamic/cms#15879), so each is asserted on its result, never on the
 * calls the widget makes.
 */
const SCAN_QUERY_COLLECTION = 'incomplete_scan_query';

/**
 * A collection whose blueprint requires `intro`, so any entry without it fails validation.
 */
function scanQueryCreateCollection(): void
{
    $collection = CollectionFacade::make(SCAN_QUERY_COLLECTION);
    $collection->title(SCAN_QUERY_COLLECTION);
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'intro' => ['type' => 'text', 'display' => 'Intro', 'validate' => ['required']],
    ]);
    $blueprint->setHandle(SCAN_QUERY_COLLECTION);
    $blueprint->setNamespace('collections.'.SCAN_QUERY_COLLECTION);
    $blueprint->save();

    if (! CollectionFacade::find(SCAN_QUERY_COLLECTION)) {
        Assert::markTestSkipped('Requires a writable content path to create the test collection.');
    }
}

/**
 * Persist an entry with a fixed id. It fails validation (no `intro`) unless `$intro` is given.
 */
function scanQueryCreateEntry(string $id, string $slug, bool $published, ?string $intro = null): void
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->id($id);
    $entry->collection(SCAN_QUERY_COLLECTION);
    $entry->slug($slug);
    $entry->published($published);
    $entry->data($intro === null ? ['title' => $slug] : ['title' => $slug, 'intro' => $intro]);
    $entry->save();
}

/**
 * A widget over the test collection, with the scan cap given or absent.
 */
function scanQueryWidget(?int $limit = null): IncompletePublishedEntriesWidgetTestDouble
{
    $widget = new IncompletePublishedEntriesWidgetTestDouble;
    $widget->setConfig($limit === null
        ? ['collections' => [SCAN_QUERY_COLLECTION]]
        : ['collections' => [SCAN_QUERY_COLLECTION], 'limit' => $limit]);

    return $widget;
}

const SCAN_QUERY_ID_1 = '00000000-0000-4000-8000-000000000001';
const SCAN_QUERY_ID_2 = '00000000-0000-4000-8000-000000000002';
const SCAN_QUERY_ID_3 = '00000000-0000-4000-8000-000000000003';
const SCAN_QUERY_ID_4 = '00000000-0000-4000-8000-000000000004';

it('scans published entries only, leaving a failing draft out of the ids and the counts', function (): void {
    scanQueryCreateCollection();
    scanQueryCreateEntry(SCAN_QUERY_ID_1, 'published-failing', true);
    scanQueryCreateEntry(SCAN_QUERY_ID_2, 'published-passing', true, 'Present');
    scanQueryCreateEntry(SCAN_QUERY_ID_3, 'draft-failing', false);

    $uncapped = scanQueryWidget()->exposeFailingCandidateEntryIds();
    $capped = scanQueryWidget(10)->exposeFailingCandidateEntryIds();

    expect($uncapped['ids'])->toBe([SCAN_QUERY_ID_1])
        ->and($uncapped['scanned'])->toBe(2)
        ->and($capped['ids'])->toBe([SCAN_QUERY_ID_1])
        ->and($capped['scanned'])->toBe(2)
        ->and($capped['total'])->toBe(2);
});

it('scans the lowest ids first under a limit and lists failing ids in ascending order', function (): void {
    scanQueryCreateCollection();

    // Slugs run opposite to the ids and the entries are saved out of order, so neither the
    // file order nor the save order can stand in for the id order.
    scanQueryCreateEntry(SCAN_QUERY_ID_3, 'alpha', true);
    scanQueryCreateEntry(SCAN_QUERY_ID_1, 'zulu', true);
    scanQueryCreateEntry(SCAN_QUERY_ID_4, 'bravo', true);
    scanQueryCreateEntry(SCAN_QUERY_ID_2, 'yankee', true);

    $all = scanQueryWidget()->exposeFailingCandidateEntryIds();
    $firstTwo = scanQueryWidget(2)->exposeFailingCandidateEntryIds();

    expect($all['ids'])->toBe([SCAN_QUERY_ID_1, SCAN_QUERY_ID_2, SCAN_QUERY_ID_3, SCAN_QUERY_ID_4])
        ->and($firstTwo['ids'])->toBe([SCAN_QUERY_ID_1, SCAN_QUERY_ID_2])
        ->and($firstTwo['scanned'])->toBe(2)
        ->and($firstTwo['total'])->toBe(4);
});

it('loads no entry for an empty id list while entries exist', function (): void {
    scanQueryCreateCollection();
    scanQueryCreateEntry(SCAN_QUERY_ID_1, 'one', true);
    scanQueryCreateEntry(SCAN_QUERY_ID_2, 'two', true, 'Present');

    $widget = scanQueryWidget();

    expect($widget->exposeEntriesWithIds([])->all())->toBe([]);

    $loaded = $widget->exposeEntriesWithIds([SCAN_QUERY_ID_2, '00000000-0000-4000-8000-0000000000ff']);
    expect($loaded->map(static fn (StatamicEntry $entry): mixed => $entry->id())->all())->toBe([SCAN_QUERY_ID_2]);
});

it('stores no rows when every published entry passes while other entries exist', function (): void {
    scanQueryCreateCollection();
    scanQueryCreateEntry(SCAN_QUERY_ID_1, 'passing', true, 'Present');
    scanQueryCreateEntry(SCAN_QUERY_ID_2, 'draft-failing', false);

    $result = scanQueryWidget()->refresh();

    expect($result['rows'])->toBe([])
        ->and($result['scan']['scanned'])->toBe(1)
        ->and($result['scan']['total'])->toBe(1);
});
