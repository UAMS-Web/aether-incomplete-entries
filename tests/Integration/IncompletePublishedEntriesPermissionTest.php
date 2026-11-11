<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileUser;
use Statamic\Auth\RoleRepository;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use UAMSWeb\Aether\IncompleteEntries\Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\IncompleteEntries\Widgets\IncompletePublishedEntries;

// Which failing entries the widget shows a user who holds only some of the entry
// permissions. Statamic checks them through its entry policy (`can('view', $entry)`),
// the call the widget's read path makes for each stored candidate, and a Statamic
// release that changes how those checks resolve (statamic/cms 6.40.0 sped them up)
// must not change who sees what. The super user, the permissionless user and the
// signed-out reader are pinned in IncompletePublishedEntriesWidgetTest.

const PERMISSION_SCAN_COLLECTION_A = 'permission_scan_a';
const PERMISSION_SCAN_COLLECTION_B = 'permission_scan_b';

/**
 * The role repository behind the facade.
 */
function permissionScanRoleRepository(): RoleRepository
{
    $repository = Role::getFacadeRoot();
    if (! $repository instanceof RoleRepository) {
        throw new RuntimeException('The role facade does not resolve to a role repository');
    }

    return $repository;
}

/**
 * The role file this test points Statamic at, so a saved role never reaches the file
 * another test process reads.
 */
function permissionScanRoleDirectory(): string
{
    static $directory = null;

    $directory ??= sys_get_temp_dir().'/incomplete-entries-roles-'.uniqid('', true);
    File::ensureDirectoryExists($directory);
    permissionScanRoleRepository()->path($directory.'/roles.yaml');

    return $directory;
}

/**
 * Persist a collection whose blueprint requires `intro`, and optionally carries an `author` field.
 */
function permissionScanCollection(string $handle, bool $withAuthor = false): void
{
    $collection = CollectionFacade::make($handle);
    $collection->title($handle);
    $collection->save();

    $fields = ['intro' => ['type' => 'text', 'validate' => ['required']]];
    if ($withAuthor) {
        $fields['author'] = ['type' => 'users', 'max_items' => 1];
    }

    $blueprint = Blueprint::makeFromFields($fields);
    $blueprint->setHandle($handle);
    $blueprint->setNamespace('collections.'.$handle);
    $blueprint->save();
}

/**
 * Persist a published entry that lacks the required `intro`, so it fails validation.
 */
function permissionScanFailingEntry(string $collection, string $title, ?string $authorId = null): void
{
    $data = ['title' => $title];
    if ($authorId !== null) {
        $data['author'] = $authorId;
    }

    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->slug(str_replace(' ', '-', strtolower($title)));
    $entry->published(true);
    $entry->data($data);
    $entry->save();
}

/**
 * Persist a user holding exactly one role made of the given permissions.
 *
 * @param  list<string>  $permissions
 */
function permissionScanUser(string $name, array $permissions): FileUser
{
    permissionScanRoleDirectory();

    $role = Role::make('role_'.$name);
    $role->permissions($permissions);
    $role->save();

    /** @var FileUser $user */
    $user = User::make();
    $user->email($name.'@example.test');
    $user->assignRole('role_'.$name);
    $user->save();

    return $user;
}

/**
 * The titles the widget shows the given user, from a scan stored before the user signed in.
 *
 * @param  list<string>  $collections
 * @return list<string>
 */
function permissionScanTitlesFor(StacheIsolatedTestCase $test, UserContract $user, array $collections): array
{
    $widget = new IncompletePublishedEntries;
    $widget->setConfig(['collections' => $collections, 'limit' => 500]);
    $widget->refresh();

    // actingAs() belongs to the test case
    $test->actingAs($user);

    $view = $widget->html();
    Assert::assertInstanceOf(View::class, $view);

    /** @var iterable<int, array{title?: string}> $entries */
    $entries = $view->getData()['entries'];

    $titles = collect($entries)
        ->map(static fn (array $row): string => (string) ($row['title'] ?? ''))
        ->sort();

    return array_values($titles->all());
}

afterEach(function (): void {
    File::deleteDirectory(permissionScanRoleDirectory());
    permissionScanRoleRepository()->path(config('statamic.users.repositories.file.paths.roles'));
    Cache::flush();
});

it("shows a user holding view on one collection only that collection's failing entries", function (): void {
    permissionScanCollection(PERMISSION_SCAN_COLLECTION_A);
    permissionScanCollection(PERMISSION_SCAN_COLLECTION_B);
    permissionScanFailingEntry(PERMISSION_SCAN_COLLECTION_A, 'Failing In A');
    permissionScanFailingEntry(PERMISSION_SCAN_COLLECTION_B, 'Failing In B');
    $both = [PERMISSION_SCAN_COLLECTION_A, PERMISSION_SCAN_COLLECTION_B];

    $viewerA = permissionScanUser('viewer_a', ['view '.PERMISSION_SCAN_COLLECTION_A.' entries']);
    $viewerB = permissionScanUser('viewer_b', ['view '.PERMISSION_SCAN_COLLECTION_B.' entries']);
    $configurer = permissionScanUser('configurer', ['configure collections']);

    expect(permissionScanTitlesFor($this, $viewerA, $both))->toBe(['Failing In A'])
        ->and(permissionScanTitlesFor($this, $viewerB, $both))->toBe(['Failing In B'])
        ->and(permissionScanTitlesFor($this, $configurer, $both))->toBe(['Failing In A', 'Failing In B']);
});

it('shows a user holding edit on a collection its entries, without a separate view permission', function (): void {
    permissionScanCollection(PERMISSION_SCAN_COLLECTION_A);
    permissionScanCollection(PERMISSION_SCAN_COLLECTION_B);
    permissionScanFailingEntry(PERMISSION_SCAN_COLLECTION_A, 'Failing In A');
    permissionScanFailingEntry(PERMISSION_SCAN_COLLECTION_B, 'Failing In B');

    $editor = permissionScanUser('editor_a', ['edit '.PERMISSION_SCAN_COLLECTION_A.' entries']);

    expect(permissionScanTitlesFor($this, $editor, [PERMISSION_SCAN_COLLECTION_A, PERMISSION_SCAN_COLLECTION_B]))
        ->toBe(['Failing In A']);
});

it("shows an editor only their own entries until they may edit other authors' entries", function (): void {
    permissionScanCollection(PERMISSION_SCAN_COLLECTION_A, withAuthor: true);

    $editor = permissionScanUser('editor_own', ['edit '.PERMISSION_SCAN_COLLECTION_A.' entries']);
    $editorAll = permissionScanUser('editor_all', [
        'edit '.PERMISSION_SCAN_COLLECTION_A.' entries',
        'edit other authors '.PERMISSION_SCAN_COLLECTION_A.' entries',
    ]);
    $viewer = permissionScanUser('viewer_all', ['view '.PERMISSION_SCAN_COLLECTION_A.' entries']);
    $other = permissionScanUser('other_author', []);

    $editorId = $editor->id();
    $otherId = $other->id();
    Assert::assertIsString($editorId);
    Assert::assertIsString($otherId);

    permissionScanFailingEntry(PERMISSION_SCAN_COLLECTION_A, 'Written By Editor', $editorId);
    permissionScanFailingEntry(PERMISSION_SCAN_COLLECTION_A, 'Written By Other', $otherId);
    $collections = [PERMISSION_SCAN_COLLECTION_A];

    expect(permissionScanTitlesFor($this, $editor, $collections))->toBe(['Written By Editor'])
        ->and(permissionScanTitlesFor($this, $editorAll, $collections))->toBe(['Written By Editor', 'Written By Other'])
        ->and(permissionScanTitlesFor($this, $viewer, $collections))->toBe(['Written By Editor', 'Written By Other']);
});
