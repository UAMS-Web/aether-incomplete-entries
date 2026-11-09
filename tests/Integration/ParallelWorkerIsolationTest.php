<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Statamic\Facades\Search;
use Statamic\Search\Comb\Index as CombIndex;
use UAMSWeb\Aether\IncompleteEntries\Tests\TestCase;

/**
 * Files a parallel worker writes outside its own throwaway directories are kept apart per
 * worker, so no two workers race to create the same directory.
 *
 * @command  vendor/bin/pest tests/Integration/ParallelWorkerIsolationTest.php
 */
it('names a search index directory per worker token', function (mixed $token, ?string $suffix): void {
    // Resolve the directory for the token
    $path = TestCase::searchIndexPath($token);

    // Expect no directory without a usable token
    if ($suffix === null) {
        expect($path)->toBeNull();

        return;
    }

    // Expect a directory ending in the token
    Assert::assertIsString($path);
    expect(str_ends_with(str_replace('\\', '/', $path), $suffix))->toBeTrue();
})->with([
    'string token' => ['4', 'statamic/search-4'],
    'integer token' => [7, 'statamic/search-7'],
    'no token' => [null, null],
    'array token' => [['4'], null],
]);

it('keeps two worker tokens on different search index directories', function (): void {
    // Expect no shared directory for two workers, the race this isolation removes
    expect(TestCase::searchIndexPath('1'))->not->toBe(TestCase::searchIndexPath('2'));
});

it("writes the local search index under this worker's directory", function (): void {
    // Resolve where a worker's own index would live, falling back to Statamic's shared default outside a parallel run
    $expected = TestCase::searchIndexPath($_SERVER['TEST_TOKEN'] ?? null) ?? storage_path('statamic/search');

    // Read the directory Statamic's local driver will create when an entry is saved
    $index = Search::index('default');
    Assert::assertInstanceOf(CombIndex::class, $index);
    $indexPath = $index->path();
    Assert::assertIsString($indexPath);

    // Expect the configured directory to be this worker's
    expect(str_replace('\\', '/', dirname($indexPath)))->toBe(str_replace('\\', '/', $expected));
});
