<?php

declare(strict_types=1);

/**
 * The README's stated requirements and license must follow `composer.json`,
 * which is what a Packagist or Marketplace visitor and Composer itself read.
 *
 * The add-on's README replaces the template's, and this test is what tells the
 * author what that README has to state.
 *
 * @command  vendor/bin/pest tests/Unit/ReadmeTest.php
 */

/**
 * The parsed `composer.json`.
 *
 * @return array{name: string, license?: string, require: array<string, string>} The manifest's name, license and requirements.
 */
function readmeComposerManifest(): array
{
    /** @var array{name: string, license?: string, require: array<string, string>} */
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The README's text.
 *
 * @return string The full contents of `README.md`.
 */
function readmeText(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
}

it('states the PHP floor that composer.json requires', function (): void {
    // Strip the constraint operators from the required PHP version
    $floor = ltrim(readmeComposerManifest()['require']['php'], '^>=~ ');

    // Expect the README to state that floor
    expect(readmeText())->toContain('PHP '.$floor.' or later');
});

it('states the statamic/cms floor that composer.json requires', function (): void {
    // Strip the constraint operators from the required Statamic version
    $floor = ltrim(readmeComposerManifest()['require']['statamic/cms'], '^>=~ ');

    // Expect the README to state that floor
    expect(readmeText())->toContain('Statamic '.$floor.' or later');
});

it('names the license that composer.json declares, once it declares one', function (): void {
    // Read the declared license, which the template leaves out until UAMS legal decides (uams-statamic#261)
    $license = readmeComposerManifest()['license'] ?? null;

    // Expect the README to name the license and point at the file, or no license to be declared
    expect($license === null || str_contains(readmeText(), $license.'. See [`LICENSE`](LICENSE)'))->toBeTrue();
});

it('requires the package by the name composer.json gives it', function (): void {
    expect(readmeText())->toContain('composer require '.readmeComposerManifest()['name']);
});

it('states the namespace convention, with the Aether level, the PSR-4 link and the nested-prefix rules', function (): void {
    // Expect the Aether shapes and when the level is not used
    expect(readmeText())->toContain('`UAMSWeb\Aether\<Product>`')
        ->and(readmeText())->toContain('`UAMSWeb\Aether\<Family>\<Product>`')
        ->and(readmeText())->toContain('The level stays `UAMSWeb\<Product>` only for an add-on outside that ecosystem')
        // Expect the PSR-4 reference
        ->and(readmeText())->toContain('(https://www.php-fig.org/psr/psr-4/)')
        // Expect the ownership rule and the neutral family example
        ->and(readmeText())->toContain('A prefix has one owner or none')
        ->and(readmeText())->toContain('`UAMSWeb\Aether\Forms\Settings\`')
        ->and(readmeText())->toContain('`UAMSWeb\Aether\Forms\Multisite\`')
        // Expect the companion shape and the longest-prefix directory rule
        ->and(readmeText())->toContain('`UAMSWeb\Aether\People\ListingsManagement\`')
        ->and(readmeText())->toContain('has no directory under `src/` named after the inner one');
});

it('points the development guide at the README paragraph on the namespace', function (): void {
    // Expect the guide to link the README step that holds the rule
    expect((string) file_get_contents(dirname(__DIR__, 2).'/docs/DEVELOPMENT.md'))
        ->toContain('(../README.md#1-generate-and-rename)');
});

it('links the files it points readers to, and they exist', function (): void {
    // Check each file the README points readers to
    foreach (['docs/DEVELOPMENT.md', 'docs/SUBMISSION.md'] as $path) {
        // Expect a link to the file, and the file on disk
        expect(readmeText())->toContain('('.$path.')')
            ->and(dirname(__DIR__, 2).'/'.$path)->toBeFile();
    }
});
