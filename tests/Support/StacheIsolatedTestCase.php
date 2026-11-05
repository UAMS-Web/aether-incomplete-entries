<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\IncompleteEntries\Tests\Support;

use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use UAMSWeb\Aether\IncompleteEntries\Tests\TestCase;

/**
 * Base for tests that persist real collections and entries: every Stache store and the
 * blueprint directory point at a throwaway directory owned by this test, and the Stache
 * index lives in an in-memory cache store, so nothing reaches disk outside it or leaks
 * into another test or parallel worker.
 *
 * Saves still hydrate the in-memory Stache, so queries exercise the real query layer.
 */
abstract class StacheIsolatedTestCase extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    /**
     * Throwaway Stache root. The upstream trait splits the path on its `/dev-null`
     * segment to re-root each store beneath it, so it must end in that.
     */
    protected string $fakeStacheDirectory;

    /**
     * Throwaway blueprint root, so a fixture blueprint never lands in the skeleton.
     */
    protected string $fakeBlueprintDirectory;

    /**
     * The blueprint directory in place before the test, restored afterwards.
     */
    private string $originalBlueprintDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Give the Stache index its own in-memory cache store for this test
        config([
            'cache.stores.stache_isolated' => ['driver' => 'array', 'serialize' => true],
            'statamic.stache.cache_store' => 'stache_isolated',
        ]);

        // Name this test's roots; the unique id inside each call keeps parallel workers and runs apart
        $this->fakeStacheDirectory = storage_path('framework/testing/stache/'.uniqid('', true).'/dev-null');
        $this->fakeBlueprintDirectory = storage_path('framework/testing/blueprints/'.uniqid('', true));

        // Start from empty directories
        File::deleteDirectory(\dirname($this->fakeStacheDirectory));
        File::deleteDirectory($this->fakeBlueprintDirectory);
        File::ensureDirectoryExists(\dirname($this->fakeStacheDirectory));

        // Re-root the Stache stores
        $this->preventSavingStacheItemsToDisk();

        // Re-root the blueprint repository, remembering where it was
        $this->originalBlueprintDirectory = Blueprint::directory();
        Blueprint::setDirectory($this->fakeBlueprintDirectory);

        // Rebuild the index from the empty roots
        Stache::clear();
    }

    protected function tearDown(): void
    {
        // Statamic's test case empties and recreates the Stache root here, so the root's
        // parent must still exist while it runs
        Blueprint::setDirectory($this->originalBlueprintDirectory);
        Stache::clear();

        parent::tearDown();

        // Delete both throwaway trees, which nothing reads after this
        File::deleteDirectory(\dirname($this->fakeStacheDirectory));
        File::deleteDirectory($this->fakeBlueprintDirectory);
    }
}
