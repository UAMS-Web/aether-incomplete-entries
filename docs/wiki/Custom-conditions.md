Written against v0.2.4.

A blueprint field can carry a display condition of the form `custom <name>:<params>`. The Control Panel decides whether to show the field by calling a JavaScript function registered under that name. The audit has to reach the same answer on the server, so that a field an editor never sees is not reported as missing. It does this with PHP ports of those functions.

## How a condition is resolved

1. A name registered in the `custom_conditions` config key is used first, so a site can also replace a shipped condition.
2. Otherwise one of the seven shipped ports below is used.
3. A name with neither is treated as hidden, and a warning naming the condition and the field is logged.

The text after `custom ` splits at the first `:`, and the parameters split at commas and are trimmed. A condition given as a bare string, with no target field, is evaluated on the sibling values alone, and the `custom ` prefix is optional there.

## Shipped conditions

Each port follows its JavaScript function, including JavaScript's rules for truthiness and strict comparison. Three are generic:

| Name | Parameters | Shows the field when |
| --- | --- | --- |
| `isNotEmpty` | none | the target value is truthy in JavaScript terms: not null, false, 0, an empty string or NaN. An empty array counts as truthy. |
| `notContainsAny` | one or more values | the target is an array holding none of the values (strict comparison), or a string containing none of them as a case-sensitive substring. With no parameters, it hides the field. |
| `selectionCount` | an operator, then one operand (two for `between`) | the number of items the target holds satisfies the comparison. Operators: `gt`, `gte`, `lt`, `lte`, `equals` (also `equal`, `eq`), `not` (also `notequal`, `notequals`, `neq`) and `between`. An operand is a number or the handle of a sibling field. |

The other four read fields named after the UAMS site's own blueprints, and matter only to content that has them:

| Name | Parameters | Reads |
| --- | --- | --- |
| `articleVisible` | none | `syndication_query`, `syndication_source` |
| `notSyndicated` | none | `syndication_query` |
| `parentOverride` | a role: `facility`, `street`, `map`, `unit`, `address` or `parking` | `parent`, `facility_query` and the `parent_*_override_query` fields |
| `parkingVisible` | a role: `facilityInherit`, `facility`, `inheritToggle`, `instructions` or `directions` | `facility`, `facility_query`, `parking_query`, `parent`, `parent_parking_override_query` and the `*_inherit_query` fields |

An unknown role hides the field for `parkingVisible`, and for `parentOverride` on a location that has a parent; a location with no parent shows it. A site whose blueprints use none of these four names is unaffected by them.

## The contract

A port of your own implements `UAMSWeb\Aether\IncompleteEntries\Contracts\CustomCondition`:

```php
public function evaluate(array $params, mixed $target, array $values): bool;
```

| Argument | Holds |
| --- | --- |
| `$params` | the trimmed parameters after the colon |
| `$target` | the value of the field the condition is keyed on |
| `$values` | the sibling field values |

It returns whether the field shows. The class is resolved from the container, so its constructor can take dependencies. Register it under the condition's name: see [Adding a custom condition](Adding-a-custom-condition.md).

## Invalid registrations

An entry in `custom_conditions` that is not a non-empty name mapped to an existing class implementing the contract throws an `InvalidArgumentException` when the audit runs, naming the entry. A `custom_conditions` value that is not an array throws too. The scan stops at that point, so fix the entry before the next scan.
