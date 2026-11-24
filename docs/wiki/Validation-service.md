Written against v0.2.4.

`UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator` is the service behind the audit. It validates one entry against its blueprint the way the Control Panel does on save, and it imports nothing from the host application. The widget and the scan command both call it, and site code can too.

## Using it

Resolve it from the container:

```php
use UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator;

$result = app(EntryValidator::class)->validate($entry, $blueprint);

if (! $result->passes()) {
    $messages = $result->errors->all();
}
```

Only fields that are visible under the blueprint's display conditions are validated, so a field an `if` or `unless` condition hides is never reported as missing. Custom conditions are evaluated as described in [Custom conditions](Custom-conditions.md).

## The result

`validate()` returns an `EntryValidationResult`:

| Member | Type | Holds |
| --- | --- | --- |
| `errors` | `Illuminate\Support\MessageBag` | The Control Panel's validation messages, keyed by field handle. |
| `attributes` | `array<string, string>` | The field labels those messages name, keyed by field handle. |
| `visibleFields` | `list<string>` | Handles of the top-level fields the display conditions show, in blueprint order. |
| `passes()` | `bool` | `true` when `errors` is empty. |

When Statamic cannot read the entry's data at all, the result carries one error under the handle `__pre_process__` and an empty `visibleFields`. A warning is logged with the entry id, collection and exception class.
