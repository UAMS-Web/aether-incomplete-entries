<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\IncompleteEntries\Tests\Support\Doubles;

use UAMSWeb\Aether\IncompleteEntries\Contracts\CustomCondition;

/**
 * A host's own custom condition: the field shows while the target equals the first param.
 */
final class HostVideoCondition implements CustomCondition
{
    public function evaluate(array $params, mixed $target, array $values): bool
    {
        return $target === ($params[0] ?? null) && ($values['allowed'] ?? true) === true;
    }
}
