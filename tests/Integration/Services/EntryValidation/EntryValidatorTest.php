<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Fields;
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidationResult;
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator;
use UAMSWeb\Aether\IncompleteEntries\Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;

// The entry validation service on its own (#275): the errors it reports, the
// fields it judges visible under the blueprint's display conditions, and the
// widget output it feeds. Isolated Stache: every test persists a collection,
// its blueprint, and entries.

const ENTRY_VALIDATOR_COLLECTION = 'entry_validator_service';

/**
 * Persist the service's collection with a blueprint built from the given fields.
 *
 * @param  array<string, array<string, mixed>>  $fields
 */
function entryValidatorCollection(array $fields): void
{
    /** @var StatamicCollection $collection */
    $collection = CollectionFacade::make(ENTRY_VALIDATOR_COLLECTION);
    $collection->title('Entry validator service');
    $collection->save();

    $blueprint = Blueprint::makeFromFields($fields);
    $blueprint->setHandle(ENTRY_VALIDATOR_COLLECTION);
    $blueprint->setNamespace('collections.'.ENTRY_VALIDATOR_COLLECTION);
    $blueprint->save();
}

/**
 * Save a published entry in the service's collection.
 *
 * @param  array<string, mixed>  $data
 */
function entryValidatorEntry(string $slug, array $data): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection(ENTRY_VALIDATOR_COLLECTION);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->save();

    return $entry;
}

/**
 * The entry's own blueprint.
 */
function entryValidatorBlueprint(StatamicEntry $entry): StatamicBlueprint
{
    $blueprint = $entry->blueprint();
    Assert::assertInstanceOf(StatamicBlueprint::class, $blueprint);

    return $blueprint;
}

/**
 * Validate an entry against its own blueprint through the service.
 */
function entryValidatorResult(StatamicEntry $entry): EntryValidationResult
{
    return app(EntryValidator::class)->validate($entry, entryValidatorBlueprint($entry));
}

/**
 * A blueprint whose `phone` is required only while `contact` is `phone`.
 *
 * The `required` + `sometimes` pairing is how a blueprint here declares a field
 * required only while visible (see docs/DECISIONS.md); without `sometimes`, the
 * rule also fires on the hidden field, which a separate case pins.
 *
 * @param  list<string>  $rules
 * @return array<string, array<string, mixed>>
 */
function entryValidatorConditionalFields(string $conditionKey, array $rules = ['required', 'sometimes']): array
{
    return [
        'contact' => ['type' => 'text'],
        'phone' => ['type' => 'text', 'validate' => $rules, $conditionKey => ['contact' => 'equals phone']],
    ];
}

it('does not report a conditionally required field its if condition hides', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('if'));

    $result = entryValidatorResult(entryValidatorEntry('kirk', ['title' => 'James Kirk', 'contact' => 'email']));

    expect($result->errors->keys())->toBe([])
        ->and($result->passes())->toBeTrue()
        ->and($result->visibleFields)->toContain('contact')
        ->and($result->visibleFields)->not->toContain('phone');
});

it('reports a conditionally required field once its if condition shows it', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('if'));

    $missing = entryValidatorResult(entryValidatorEntry('spock', ['title' => 'Spock', 'contact' => 'phone']));
    $filled = entryValidatorResult(entryValidatorEntry('mccoy', ['title' => 'Leonard McCoy', 'contact' => 'phone', 'phone' => '501-555-0100']));

    expect($missing->errors->keys())->toBe(['phone'])
        ->and($missing->passes())->toBeFalse()
        ->and($missing->attributes)->toHaveKey('phone')
        ->and($missing->visibleFields)->toContain('phone')
        ->and($filled->errors->keys())->toBe([])
        ->and($filled->visibleFields)->toContain('phone');
});

it('applies an unless condition on both sides of it', function (): void {
    entryValidatorCollection(entryValidatorConditionalFields('unless'));

    // `unless contact equals phone` hides the field exactly when the if form shows it
    $hidden = entryValidatorResult(entryValidatorEntry('scott', ['title' => 'Montgomery Scott', 'contact' => 'phone']));
    $shown = entryValidatorResult(entryValidatorEntry('uhura', ['title' => 'Nyota Uhura', 'contact' => 'email']));

    expect($hidden->errors->keys())->toBe([])
        ->and($hidden->visibleFields)->not->toContain('phone')
        ->and($shown->errors->keys())->toBe(['phone'])
        ->and($shown->visibleFields)->toContain('phone');
});

it('still reports a hidden field whose rules require it without sometimes', function (): void {
    // The value is left out, as the Control Panel leaves a hidden field out of its
    // submission, but a bare `required` rejects an absent key all the same
    entryValidatorCollection(entryValidatorConditionalFields('if', ['required']));

    $result = entryValidatorResult(entryValidatorEntry('rand', ['title' => 'Janice Rand', 'contact' => 'email']));

    expect($result->errors->keys())->toBe(['phone'])
        ->and($result->visibleFields)->not->toContain('phone');
});

it('lists the visible fields in blueprint order', function (): void {
    entryValidatorCollection([
        'contact' => ['type' => 'text'],
        'phone' => ['type' => 'text', 'if' => ['contact' => 'equals phone']],
        'email' => ['type' => 'text', 'if' => ['contact' => 'equals email']],
        'notes' => ['type' => 'textarea'],
    ]);

    $result = entryValidatorResult(entryValidatorEntry('sulu', ['title' => 'Hikaru Sulu', 'contact' => 'email']));

    expect(array_values(array_intersect($result->visibleFields, ['contact', 'phone', 'email', 'notes'])))
        ->toBe(['contact', 'email', 'notes']);
});

it('gives the widget the errors and labels it reports, for a failing and a passing entry', function (): void {
    entryValidatorCollection([
        'contact' => ['type' => 'text', 'display' => 'Contact method'],
        'phone' => ['type' => 'text', 'display' => 'Phone', 'validate' => ['required', 'sometimes'], 'if' => ['contact' => 'equals phone']],
        'rank' => ['type' => 'text', 'display' => 'Rank', 'validate' => ['required']],
    ]);

    $widget = new IncompletePublishedEntriesWidgetTestDouble;

    // Each case states its expected errors outright: the widget delegates to the
    // service, so comparing the two to each other alone could not catch a change
    $cases = [
        'failing' => [entryValidatorEntry('chekov', ['title' => 'Pavel Chekov', 'contact' => 'phone']), ['phone', 'rank']],
        'passing' => [entryValidatorEntry('chapel', ['title' => 'Christine Chapel', 'contact' => 'email', 'rank' => 'Nurse']), []],
    ];

    foreach ($cases as [$entry, $expectedErrors]) {
        $blueprint = entryValidatorBlueprint($entry);

        $fromWidget = $widget->exposeValidateEntry($entry, $blueprint);
        $fromService = app(EntryValidator::class)->validate($entry, $blueprint);

        expect($fromService->errors->keys())->toBe($expectedErrors)
            ->and($fromWidget['errors']->toArray())->toBe($fromService->errors->toArray())
            ->and($fromWidget['attributes'])->toBe($fromService->attributes);
    }
});

it('reports unreadable entry data as a single error and no visible fields', function (): void {
    entryValidatorCollection(['rank' => ['type' => 'text', 'validate' => ['required']]]);

    $entry = entryValidatorEntry('leslie', ['title' => 'Leslie']);
    $blueprint = entryValidatorBlueprint($entry);

    // The first fields() call, which sanitizes replicator values, succeeds; the
    // preProcess pass that follows throws, as malformed stored data does
    $realFields = $blueprint->fields();
    $calls = 0;
    $failing = Mockery::mock($blueprint)->makePartial();
    $failing->shouldReceive('fields')->andReturnUsing(function () use (&$calls, $realFields): Fields {
        $calls++;
        if ($calls > 1) {
            throw new RuntimeException('Malformed field data');
        }

        return $realFields;
    });

    Assert::assertInstanceOf(StatamicBlueprint::class, $failing);

    // Expect one warning that names the failure, the entry and its collection
    Log::shouldReceive('warning')->once()->with(
        'EntryValidator: field preProcess failed; treating entry as failing validation',
        [
            'message' => 'Malformed field data',
            'entryId' => $entry->id(),
            'collection' => ENTRY_VALIDATOR_COLLECTION,
            'exception' => RuntimeException::class,
        ]
    );

    $result = app(EntryValidator::class)->validate($entry, $failing);

    expect($result->errors->keys())->toBe(['__pre_process__'])
        ->and($result->errors->get('__pre_process__'))->toBe(['Published entry data could not be read for validation. Fix malformed fields in the entry editor.'])
        ->and($result->attributes)->toBe(['__pre_process__' => 'Entry data'])
        ->and($result->visibleFields)->toBe([]);
});

it('hands a dated entry its date in the format the blueprint expects', function (bool $timeEnabled, string $stored, string $expected): void {
    $collection = CollectionFacade::make(ENTRY_VALIDATOR_COLLECTION);
    $collection->title('Entry validator service');
    $collection->dated(true);
    $collection->save();

    $blueprint = Blueprint::makeFromFields(['date' => ['type' => 'date', 'time_enabled' => $timeEnabled]]);
    $blueprint->setHandle(ENTRY_VALIDATOR_COLLECTION);
    $blueprint->setNamespace('collections.'.ENTRY_VALIDATOR_COLLECTION);
    $blueprint->save();

    $entry = entryValidatorEntry('leslie', ['title' => 'Leslie']);
    $entry->date(Carbon::parse($stored));
    $entry->save();

    expect(app(EntryValidator::class)->extractValues($entry)['date'])->toBe($expected);
})->with([
    'date only' => [false, '2026-03-04', '2026-03-04'],
    'date and time' => [true, '2026-03-04 05:06:07', '2026-03-04 05:06:07'],
    'a time on a blueprint without time' => [false, '2026-03-04 05:06:07', '2026-03-04 05:06:07'],
]);

it('coerces a malformed replicator that follows an absent one', function (): void {
    entryValidatorCollection([
        'first' => ['type' => 'replicator', 'sets' => []],
        'second' => ['type' => 'replicator', 'sets' => []],
    ]);

    $blueprint = entryValidatorBlueprint(entryValidatorEntry('leslie', ['title' => 'Leslie']));

    expect(app(EntryValidator::class)->sanitizeMalformedReplicatorValues($blueprint, ['second' => 'junk']))
        ->toBe(['second' => []]);
});

/**
 * One field of the given type and options, required.
 *
 * @param  array<mixed>  $field
 * @return array<string, array<string, mixed>>
 */
function entryValidatorRequiredField(string $handle, array $field): array
{
    $config = ['validate' => ['required']];
    foreach ($field as $key => $value) {
        $config[(string) $key] = $value;
    }

    return [$handle => $config];
}

it('judges each field on its own options, entry after entry in one process', function (): void {
    // Field option defaults are shared work inside Statamic (statamic/cms 6.40.0 builds
    // them once), so a rule or condition one field sets must not reach a sibling, and one
    // entry's judgment must not shape the next
    entryValidatorCollection([
        'mode' => ['type' => 'text'],
        'always' => ['type' => 'text', 'validate' => ['required']],
        'number' => ['type' => 'text', 'validate' => ['sometimes', 'integer']],
        'when_yes' => ['type' => 'text', 'validate' => ['required', 'sometimes'], 'if' => ['mode' => 'equals yes']],
        'unless_yes' => ['type' => 'text', 'validate' => ['required', 'sometimes'], 'unless' => ['mode' => 'equals yes']],
        'free' => ['type' => 'text'],
    ]);

    $first = entryValidatorResult(entryValidatorEntry('first', ['title' => 'First', 'mode' => 'yes', 'number' => 'abc']));
    $second = entryValidatorResult(entryValidatorEntry('second', ['title' => 'Second', 'mode' => 'no', 'always' => 'set', 'number' => '7']));
    $third = entryValidatorResult(entryValidatorEntry('third', ['title' => 'Third', 'mode' => 'yes', 'always' => 'set', 'when_yes' => 'set']));
    $again = entryValidatorResult(entryValidatorEntry('again', ['title' => 'Again', 'mode' => 'yes', 'number' => 'abc']));

    expect($first->errors->keys())->toBe(['always', 'number', 'when_yes'])
        ->and($second->errors->keys())->toBe(['unless_yes'])
        ->and($third->errors->keys())->toBe([])
        ->and($again->errors->keys())->toBe($first->errors->keys())
        ->and($second->visibleFields)->not->toContain('when_yes')
        ->and($first->visibleFields)->not->toContain('unless_yes')
        ->and($first->visibleFields)->toContain('free');
});

it('reports a missing required value and accepts a present one for each choice and reference fieldtype', function (string $handle, array $field, mixed $present): void {
    // An assets field reads its container while the entry is preprocessed; the disk is a
    // directory of its own per run, removed afterwards, so no worker shares it
    $disk = 'pinned_assets_'.uniqid();
    $root = sys_get_temp_dir().'/'.$disk;
    if ($field['type'] === 'assets') {
        config(['filesystems.disks.'.$disk => ['driver' => 'local', 'root' => $root]]);
        Storage::disk($disk)->put('one.jpg', 'image');
        $container = AssetContainer::make($disk);
        Assert::assertInstanceOf(Statamic\Assets\AssetContainer::class, $container);
        $container->disk($disk);
        $container->save();
        $field['container'] = $disk;
    }

    entryValidatorCollection(entryValidatorRequiredField($handle, $field));

    $missing = entryValidatorResult(entryValidatorEntry('missing', ['title' => 'Missing']));
    $filled = entryValidatorResult(entryValidatorEntry('filled', ['title' => 'Filled', $handle => $present]));

    File::deleteDirectory($root);

    expect($missing->errors->keys())->toBe([$handle])
        ->and($filled->errors->keys())->toBe([]);
})->with([
    'select' => ['pick', ['type' => 'select', 'options' => ['a' => 'A', 'b' => 'B']], 'a'],
    'checkboxes' => ['boxes', ['type' => 'checkboxes', 'options' => ['a' => 'A', 'b' => 'B']], ['a']],
    'link' => ['ref', ['type' => 'link'], 'https://example.test/page'],
    'assets' => ['files', ['type' => 'assets', 'max_files' => 1], ['one.jpg']],
]);
