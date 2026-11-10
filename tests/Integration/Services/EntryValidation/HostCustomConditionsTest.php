<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\CustomConditionEvaluator;
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator;
use UAMSWeb\Aether\IncompleteEntries\Tests\Support\Doubles\HostVideoCondition;

// A host registers a server-side port for each custom condition of its own in the
// `custom_conditions` config key (#20); the seven shipped ports stay the default.

const HOST_CONDITIONS_CONFIG = 'aether-incomplete-entries.custom_conditions';

it('ships no host condition by default and evaluates exactly the seven listed ones', function (): void {
    expect(config(HOST_CONDITIONS_CONFIG))->toBe([])
        ->and((new CustomConditionEvaluator)->names())->toBe(CustomConditionEvaluator::SUPPORTED)
        ->and((new CustomConditionEvaluator)->evaluate('hasVideo', ['yes'], 'yes', []))->toBeNull();
});

it('evaluates a condition the host registers, with its params, target and sibling values', function (): void {
    config([HOST_CONDITIONS_CONFIG => ['hasVideo' => HostVideoCondition::class]]);

    $evaluator = new CustomConditionEvaluator;

    expect($evaluator->evaluate('hasVideo', ['yes'], 'yes', []))->toBeTrue()
        ->and($evaluator->evaluate('hasVideo', ['yes'], 'no', []))->toBeFalse()
        ->and($evaluator->evaluate('hasVideo', ['yes'], 'yes', ['allowed' => false]))->toBeFalse()
        ->and($evaluator->evaluate('hasVideo', [], 'yes', []))->toBeFalse();
});

it('routes a registered condition through the validator without reading it as hidden', function (): void {
    config([HOST_CONDITIONS_CONFIG => ['hasVideo' => HostVideoCondition::class]]);
    $log = Log::spy();

    $passes = app(EntryValidator::class)->evaluateCondition('format', 'custom hasVideo:video', ['format' => 'video']);

    expect($passes)->toBeTrue();
    $log->shouldNotHaveReceived('warning');
});

it('still evaluates the shipped conditions beside a registered one', function (): void {
    config([HOST_CONDITIONS_CONFIG => ['hasVideo' => HostVideoCondition::class]]);

    expect((new CustomConditionEvaluator)->evaluate('notSyndicated', [], null, ['syndication_query' => false]))->toBeTrue();
});

it('lets a registered condition replace a shipped one of the same name', function (): void {
    config([HOST_CONDITIONS_CONFIG => ['isNotEmpty' => HostVideoCondition::class]]);

    // The shipped isNotEmpty reads 'x' as truthy; the host's port compares it with the first param
    expect((new CustomConditionEvaluator)->evaluate('isNotEmpty', ['y'], 'x', []))->toBeFalse();
});

it("has an evaluator for every condition name it lists, the host's included", function (): void {
    config([HOST_CONDITIONS_CONFIG => ['hasVideo' => HostVideoCondition::class]]);

    $evaluator = new CustomConditionEvaluator;

    expect($evaluator->names())->toBe([...CustomConditionEvaluator::SUPPORTED, 'hasVideo']);

    foreach ($evaluator->names() as $name) {
        expect($evaluator->evaluate($name, ['x', '1'], 'x', []))->toBeBool("{$name} has no evaluator");
    }
});

it('lists a name once when the host replaces a shipped condition', function (): void {
    config([HOST_CONDITIONS_CONFIG => ['isNotEmpty' => HostVideoCondition::class]]);

    expect((new CustomConditionEvaluator)->names())->toBe(CustomConditionEvaluator::SUPPORTED);
});

it('rejects a registration that is not a name mapped to a condition class', function (mixed $configured, string $message): void {
    config([HOST_CONDITIONS_CONFIG => $configured]);

    expect(fn (): ?bool => (new CustomConditionEvaluator)->evaluate('anything', [], null, []))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'a class that does not exist' => [['hasVideo' => 'App\Missing'], 'entry "hasVideo" must map'],
    'a class that is not a condition' => [['hasVideo' => stdClass::class], 'entry "hasVideo" must map'],
    'a value that is not a string' => [['hasVideo' => 3], 'entry "hasVideo" must map'],
    'a list instead of names' => [[HostVideoCondition::class], 'entry 0 must map'],
    'an empty name' => [['' => HostVideoCondition::class], 'entry "" must map'],
    'a config that is not an array' => ['nope', 'must be an array'],
]);
