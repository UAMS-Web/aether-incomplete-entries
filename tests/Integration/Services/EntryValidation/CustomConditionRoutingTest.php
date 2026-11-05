<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Statamic\Fields\Field;
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator;

// How the entry validator routes a `custom` field condition to its server-side
// port (#508), and the guard that every condition the blueprints use has one.

it('routes a custom condition through its port, parsing params as the editor does', function (): void {
    $validator = app(EntryValidator::class);

    // Params run from the first colon to the next one, comma-split and trimmed
    expect($validator->evaluateCondition('picks', 'custom selectionCount: lt , 3', ['picks' => ['a', 'b']]))->toBeTrue()
        ->and($validator->evaluateCondition('picks', 'custom selectionCount:lt,2', ['picks' => ['a', 'b']]))->toBeFalse()
        ->and($validator->evaluateCondition('picks', 'custom selectionCount:lt,3:ignored', ['picks' => ['a', 'b']]))->toBeTrue()
        ->and($validator->evaluateCondition('syndication_query', 'custom notSyndicated', ['syndication_query' => false]))->toBeTrue();
});

it('reads an unported custom condition as hidden, like the editor, and logs it', function (): void {
    $log = Log::spy();

    $passes = app(EntryValidator::class)->evaluateCondition('picks', 'custom noSuchCondition:a', ['picks' => 'a']);

    expect($passes)->toBeFalse();

    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'no server-side port')
            && $context === ['condition' => 'noSuchCondition', 'field' => 'picks']);
});

it('evaluates a custom condition written as the whole condition block, as the editor does', function (string $block, mixed $syndicated, bool $visible): void {
    $field = new Field('teaser', ['type' => 'text', $block => 'custom notSyndicated']);

    expect(app(EntryValidator::class)->fieldPassesConditions($field, ['syndication_query' => $syndicated]))->toBe($visible);
})->with([
    'if shows when it passes' => ['if', false, true],
    'if hides when it fails' => ['if', true, false],
    'unless hides when it passes' => ['unless', false, false],
    'unless shows when it fails' => ['unless', true, true],
    'hide_when hides when it passes' => ['hide_when', false, false],
]);

it('reads a string condition block without the custom prefix as a custom condition', function (): void {
    $field = new Field('teaser', ['type' => 'text', 'if' => 'notSyndicated']);

    expect(app(EntryValidator::class)->fieldPassesConditions($field, ['syndication_query' => false]))->toBeTrue()
        ->and(app(EntryValidator::class)->fieldPassesConditions($field, ['syndication_query' => true]))->toBeFalse();
});

it('hides a field whose string condition block names no ported condition', function (): void {
    $log = Log::spy();

    $field = new Field('teaser', ['type' => 'text', 'if' => 'not a condition']);

    expect(app(EntryValidator::class)->fieldPassesConditions($field, []))->toBeFalse();

    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context === ['condition' => 'not a condition', 'field' => 'teaser']);
});
