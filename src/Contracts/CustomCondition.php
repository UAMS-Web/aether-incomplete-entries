<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\IncompleteEntries\Contracts;

/**
 * A server-side port of one custom field condition that a host registers in the
 * Control Panel (`Statamic.$conditions.add(...)`), so a field the editor shows is
 * validated and a field it hides is not.
 *
 * A host registers it under the condition's name in the `custom_conditions` config
 * key; the add-on resolves it from the container, so a port can take dependencies.
 * It must follow its JavaScript function, including JavaScript's truthiness and
 * strict comparisons.
 */
interface CustomCondition
{
    /**
     * Whether the field shows.
     *
     * @param  list<string>  $params  The trimmed parameters after the colon
     * @param  mixed  $target  The value of the field the condition is keyed on
     * @param  array<string, mixed>  $values  The sibling field values
     */
    public function evaluate(array $params, mixed $target, array $values): bool;
}
