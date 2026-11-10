<?php

declare(strict_types=1);

use Statamic\Facades\Path;
use Statamic\Facades\Stache;

/**
 * Every Stache store a test saves to sits inside that test's throwaway directory, with a
 * path that is valid on every platform.
 *
 * @command  vendor/bin/pest tests/Integration/StacheIsolationTest.php
 */
it('points every Stache store inside the throwaway directory', function (): void {
    // Read the throwaway root and each store's directory
    $root = Path::tidy($this->fakeStacheDirectory);
    $directories = Stache::stores()->map(fn ($store): string => Path::tidy($store->directory()))->values()->all();

    // Expect stores to exist, or the checks below would pass over nothing
    expect($directories)->not->toBeEmpty();

    foreach ($directories as $directory) {
        // Expect the store under the root, never beside or above it
        expect(str_starts_with($directory, $root.'/'))->toBeTrue("{$directory} is outside {$root}");
    }
});

it('keeps a drive letter and the fixtures path out of every store directory', function (): void {
    // Read what each store directory adds to the throwaway root
    $root = Path::tidy($this->fakeStacheDirectory);
    $relatives = Stache::stores()
        ->map(fn ($store): string => substr(Path::tidy($store->directory()), \strlen($root) + 1))
        ->values()
        ->all();

    foreach ($relatives as $relative) {
        // Expect a relative path with no colon (a Windows drive letter) and no repeated fixtures root
        expect($relative)->not->toContain(':')
            ->and($relative)->not->toContain('__fixtures__')
            ->and($relative)->not->toContain('dev-null');
    }
});
