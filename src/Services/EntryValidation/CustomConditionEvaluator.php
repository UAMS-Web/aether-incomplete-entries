<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation;

use InvalidArgumentException;
use UAMSWeb\Aether\IncompleteEntries\Contracts\CustomCondition;

/**
 * Server-side ports of the custom field conditions the Control Panel registers in
 * `resources/js/conditions/` (#508), so a field the editor shows is validated and a
 * field it hides is not.
 *
 * Each port follows its JavaScript function, including JavaScript's truthiness and
 * strict comparisons, and reads the same inputs Statamic passes it: `target` (the
 * value of the field the condition is keyed on), the trimmed `params` after the
 * colon, and the sibling `values`. Only the conditions a blueprint or fieldset uses
 * are ported; {@see self::SUPPORTED} lists them, and an unlisted name returns null.
 *
 * A host with custom conditions of its own registers a {@see CustomCondition} per name
 * in the `custom_conditions` config key. A registered name is tried first, so a host
 * can also replace one of the conditions listed here.
 */
class CustomConditionEvaluator
{
    /**
     * The custom condition names this class evaluates.
     *
     * @var list<string>
     */
    // Coverage reports no executed line for a constant initializer, so Pest marks these untested; a test asserts the list exactly
    // @pest-mutate-ignore: RemoveArrayItem
    public const array SUPPORTED = [
        'articleVisible',
        'isNotEmpty',
        'notContainsAny',
        'notSyndicated',
        'parentOverride',
        'parkingVisible',
        'selectionCount',
    ];

    /**
     * Evaluate a custom condition the way its registered JavaScript function does.
     *
     * @param  string  $name  The condition name, e.g. `parkingVisible`
     * @param  list<string>  $params  The trimmed parameters after the colon
     * @param  mixed  $target  The value of the field the condition is keyed on
     * @param  array<string, mixed>  $values  The sibling field values
     * @return bool|null Whether the field shows, or null for a condition this class does not port
     */
    public function evaluate(string $name, array $params, mixed $target, array $values): ?bool
    {
        // A condition the host registered takes precedence
        $registered = $this->registeredConditions()[$name] ?? null;

        if ($registered !== null) {
            return $registered->evaluate($params, $target, $values);
        }

        return match ($name) {
            'articleVisible' => $this->articleVisible($values),
            'isNotEmpty' => $this->isTruthy($target),
            'notContainsAny' => $this->notContainsAny($params, $target),
            'notSyndicated' => $this->isFalse($values['syndication_query'] ?? null),
            // A missing role and any unknown role take the same default branch, so the empty string cannot be told apart from another
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            'parentOverride' => $this->parentOverride($params[0] ?? '', $values),
            // A missing role and any unknown role take the same default branch, so the empty string cannot be told apart from another
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            'parkingVisible' => $this->parkingVisible($params[0] ?? '', $values),
            'selectionCount' => $this->selectionCount($params, $target, $values),
            default => null,
        };
    }

    /**
     * Every condition name this class evaluates: the listed ones and the host's.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return [...self::SUPPORTED, ...array_diff(array_keys($this->registeredConditions()), self::SUPPORTED)];
    }

    /**
     * The conditions the host registered in the `custom_conditions` config key.
     *
     * @return array<string, CustomCondition>
     *
     * @throws InvalidArgumentException When an entry is not a condition name mapped to a class that implements the contract
     */
    private function registeredConditions(): array
    {
        $configured = config('aether-incomplete-entries.custom_conditions', []);

        if (! \is_array($configured)) {
            throw new InvalidArgumentException('The aether-incomplete-entries.custom_conditions config must be an array of condition names to classes.');
        }

        $registered = [];

        foreach ($configured as $name => $class) {
            $condition = \is_string($class) && class_exists($class) ? app()->make($class) : null;

            if (! \is_string($name) || $name === '' || ! $condition instanceof CustomCondition) {
                throw new InvalidArgumentException(sprintf(
                    'The aether-incomplete-entries.custom_conditions entry %s must map a condition name to a class that implements %s.',
                    json_encode($name),
                    CustomCondition::class,
                ));
            }

            $registered[$name] = $condition;
        }

        return $registered;
    }

    /**
     * `articleVisible`: an original article, or one syndicated from a non-NCI source.
     *
     * @param  array<string, mixed>  $values
     */
    private function articleVisible(array $values): bool
    {
        if ($this->isFalse($values['syndication_query'] ?? null)) {
            return true;
        }

        return ($values['syndication_source'] ?? null) === 'other';
    }

    /**
     * `notContainsAny`: the value shares no item with, and contains none of, the params.
     *
     * @param  list<string>  $params
     */
    private function notContainsAny(array $params, mixed $target): bool
    {
        // The editor's function refuses a condition with no params
        if ($params === []) {
            return false;
        }

        // Check if the target is an array: no item may be a param, compared strictly
        // as JavaScript's `includes` does, so the number 1 is not the param '1'
        if (\is_array($target)) {
            return array_all($target, fn (mixed $item): bool => ! \in_array($item, $params, true));
        }

        // Coerce the target to a string as JavaScript's String() does
        $targetString = match (true) {
            $target === null => '',
            \is_bool($target) => $target ? 'true' : 'false',
            \is_scalar($target) => (string) $target,
            // An object has no string form and reads as empty; another fixed string differs only for a param equal to it
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            default => '',
        };

        // True when no param appears as a literal, case-sensitive substring
        return array_all($params, fn (string $param): bool => ! str_contains($targetString, $param));
    }

    /**
     * `parentOverride`: which location address groups a child location owns.
     *
     * @param  array<string, mixed>  $values
     */
    private function parentOverride(string $role, array $values): bool
    {
        $isChild = $this->hasValue($values['parent'] ?? null);
        $overridesAddress = $this->isTrue($values['parent_address_override_query'] ?? null);

        // A standalone location owns every group; a child owns one it overrides
        $owns = fn (string $group): bool => ! $isChild
            || ($overridesAddress && $this->isTrue($values['parent_address_override_'.$group.'_query'] ?? null));

        $ownsFacility = $owns('facility');
        $inFacility = $this->isTrue($values['facility_query'] ?? null);

        switch ($role) {
            case 'facility':
                return $ownsFacility;
            case 'street':
            case 'map':
                return $owns($role) && ($isChild
                    ? ! $ownsFacility || ! $inFacility
                    : $this->isFalse($values['facility_query'] ?? null));
            case 'unit':
                return $owns('unit') && ($isChild ? ! $ownsFacility || $inFacility : $inFacility);
        }

        if (! $isChild) {
            return true;
        }

        return match ($role) {
            'address' => $overridesAddress,
            'parking' => $this->isTrue($values['parent_parking_override_query'] ?? null),
            default => false,
        };
    }

    /**
     * `parkingVisible`: the parking fields that apply to a location.
     *
     * @param  array<string, mixed>  $values
     */
    private function parkingVisible(string $role, array $values): bool
    {
        $contained = $this->isTrue($values['facility_query'] ?? null) && $this->hasValue($values['facility'] ?? null);
        $standaloneWithParking = $this->isFalse($values['facility_query'] ?? null)
            && $this->isTrue($values['parking_query'] ?? null);
        $childOverride = $this->hasValue($values['parent'] ?? null)
            && $this->isTrue($values['parent_parking_override_query'] ?? null);

        $parkingApplies = $contained || $standaloneWithParking || $childOverride;

        return match ($role) {
            'facilityInherit' => $contained,
            'facility' => ($contained && $this->isFalse($values['parking_facility_inherit_query'] ?? null))
                || $standaloneWithParking
                || $childOverride,
            'inheritToggle' => $parkingApplies,
            'instructions' => $this->isFalse($values['parking_instructions_inherit_query'] ?? null) && $parkingApplies,
            'directions' => $this->isFalse($values['directions_from_parking_inherit_query'] ?? null) && $parkingApplies,
            default => false,
        };
    }

    /**
     * `selectionCount`: compare how many items the target holds against an operand.
     *
     * @param  list<string>  $params  The operator, then one operand (two for `between`)
     * @param  array<string, mixed>  $values
     */
    private function selectionCount(array $params, mixed $target, array $values): bool
    {
        if ($params === []) {
            return false;
        }

        // An array counts its items, an empty value counts none, anything else counts one
        $count = match (true) {
            \is_array($target) => \count($target),
            $target === null, $target === '' => 0,
            default => 1,
        };

        // NAN stands for JavaScript's NaN: every comparison with it fails except "not equal"
        $first = $this->numericOperand($params[1] ?? null, $values);

        switch ($params[0]) {
            case 'gt':
                return $count > $first;
            case 'gte':
                return $count >= $first;
            case 'lt':
                return $count < $first;
            case 'lte':
                return $count <= $first;
            case 'equals':
            case 'equal':
            case 'eq':
                return (float) $count === $first;
            case 'not':
            case 'notequal':
            case 'notequals':
            case 'neq':
                return (float) $count !== $first;
            case 'between':
                $second = $this->numericOperand($params[2] ?? null, $values);

                // A NAN bound makes either comparison false, as in JavaScript
                return $count >= $first && $count <= $second;
        }

        return false;
    }

    /**
     * Resolve a `selectionCount` operand: a number literal, or a sibling field read
     * as a number (an array by its item count), else NAN.
     *
     * Two known differences from `resolveNumericOperand` in the editor, accepted
     * because no blueprint or fieldset uses a field-handle operand today (every
     * use is a literal): the editor falls back to the root values when the
     * sibling lookup finds nothing, which this class has no access to; and it
     * reads hexadecimal, `Infinity` and an object as `Number()` does, where this
     * reads them as NAN or, for an associative array, as its item count.
     *
     * @param  array<string, mixed>  $values
     */
    private function numericOperand(?string $token, array $values): float
    {
        // A null token reads as blank; trim(null) differs only by a deprecation notice
        // @pest-mutate-ignore: RemoveStringCast
        $token = trim((string) $token);

        if ($token === '') {
            return NAN;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $token) === 1) {
            return (float) $token;
        }

        $raw = data_get($values, $token);

        // Coverage reports no executed line for the constant initializer or this match head, so Pest marks the mutants untested; the SUPPORTED test and the operand rows assert them
        // @pest-mutate-ignore: TrueToFalse
        return match (true) {
            $raw === null => NAN,
            // The declared float return type widens an int, so the casts here change no result
            // @pest-mutate-ignore: RemoveDoubleCast
            \is_array($raw) => (float) \count($raw),
            \is_bool($raw) => $raw ? 1.0 : 0.0,
            // The declared float return type widens an int, so the cast changes no result
            // @pest-mutate-ignore: RemoveDoubleCast
            \is_int($raw), \is_float($raw) => (float) $raw,
            // JavaScript's Number() reads a blank string as zero
            \is_string($raw) && trim($raw) === '' => 0.0,
            // PHP's numeric strings already allow surrounding whitespace, so unwrapping either trim changes no result
            // @pest-mutate-ignore: UnwrapTrim
            \is_string($raw) && is_numeric(trim($raw)) => (float) trim($raw),
            default => NAN,
        };
    }

    /**
     * JavaScript truthiness: false for null, false, 0, '' and NaN; true otherwise,
     * including for an empty array.
     */
    private function isTruthy(mixed $value): bool
    {
        if (\is_float($value)) {
            return $value !== 0.0 && ! is_nan($value);
        }

        return ! \in_array($value, [null, false, 0, ''], true);
    }

    /**
     * The editor's spellings of an answered "yes".
     */
    private function isTrue(mixed $value): bool
    {
        return \in_array($value, [true, 'true', 1, 1.0], true);
    }

    /**
     * The editor's spellings of an answered "no".
     */
    private function isFalse(mixed $value): bool
    {
        return \in_array($value, [false, 'false', 0, 0.0], true);
    }

    /**
     * Whether a value is set: a non-empty array, or anything but null and ''.
     */
    private function hasValue(mixed $value): bool
    {
        return \is_array($value) ? $value !== [] : $value !== null && $value !== '';
    }
}
