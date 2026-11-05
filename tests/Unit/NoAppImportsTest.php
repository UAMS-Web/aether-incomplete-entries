<?php

declare(strict_types=1);

/**
 * The add-on runs in any host, so nothing under `src/` may reach into the `App\` namespace
 * (UAMS-Web/aether-incomplete-entries#3).
 *
 * @command  vendor/bin/pest tests/Unit/NoAppImportsTest.php
 */

/**
 * Every `App\` reference in a source string: an import, a fully qualified name, or a string
 * naming a class.
 *
 * @return list<string> The matching lines, each prefixed with its line number.
 */
function appNamespaceReferences(string $source): array
{
    $hits = [];

    foreach (preg_split('/\R/', $source) ?: [] as $number => $line) {
        // A reference starts `App\` at a name boundary: a leading backslash is a hit, but a
        // segment inside another namespace (`UAMSWeb\...\App\`) or a longer word (`WebApp\`) is not
        if (preg_match('/(?<![A-Za-z0-9_])(?<![A-Za-z0-9_]\\\\)App\\\\{1,2}[A-Za-z]/', $line) === 1) {
            $hits[] = ($number + 1).': '.trim($line);
        }
    }

    return $hits;
}

it('finds an App import, so the sweep below can fail', function (): void {
    // Positive control: each shape the sweep must catch
    expect(appNamespaceReferences("use App\\Widgets\\Thing;\n"))->toHaveCount(1)
        ->and(appNamespaceReferences("\$x = \\App\\Models\\User::class;\n"))->toHaveCount(1)
        ->and(appNamespaceReferences("\$x = 'App\\\\Widgets\\\\Thing';\n"))->toHaveCount(1);

    // Negative control: this add-on's own namespace and unrelated words are not hits
    expect(appNamespaceReferences("use UAMSWeb\\Aether\\IncompleteEntries\\Widgets\\Thing;\n"))->toBe([])
        ->and(appNamespaceReferences("use Vendor\\App\\Thing;\n"))->toBe([])
        ->and(appNamespaceReferences("// the Application and WebApp classes\n"))->toBe([]);
});

it('imports nothing from the App namespace', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    $scanned = 0;
    $found = [];
    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $scanned++;
        foreach (appNamespaceReferences((string) file_get_contents($file->getPathname())) as $hit) {
            $found[] = $file->getFilename().':'.$hit;
        }
    }

    // The sweep must have read the add-on's classes, or its silence proves nothing
    expect($scanned)->toBeGreaterThan(5)
        ->and($found)->toBe([]);
});
