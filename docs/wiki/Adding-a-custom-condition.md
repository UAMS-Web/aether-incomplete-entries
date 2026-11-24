Written against v0.2.4.

A blueprint field with a `custom hasVideo` style display condition is shown or hidden by a JavaScript function the site registers in the Control Panel. The audit needs the same answer in PHP. Without it, the audit logs a warning and treats the field as hidden, so the field is never checked.

## Steps

1. Create a class that implements the contract and follows the JavaScript function, including its truthiness and strict comparisons:

   ```php
   namespace App\Conditions;

   use UAMSWeb\Aether\IncompleteEntries\Contracts\CustomCondition;

   class HasVideo implements CustomCondition
   {
       public function evaluate(array $params, mixed $target, array $values): bool
       {
           return ! empty($values['video']);
       }
   }
   ```

   `$params` holds the trimmed parameters after the colon, `$target` the value of the field the condition is keyed on, and `$values` the sibling field values. The class is resolved from the container, so its constructor can take dependencies.

2. Register it in `config/aether-incomplete-entries.php` under the condition's name:

   ```php
   'custom_conditions' => [
       'hasVideo' => \App\Conditions\HasVideo::class,
   ],
   ```

3. Run `php artisan cp:scan-incomplete-published-entries` and check the log. The warning `custom field condition has no server-side port` should no longer name the condition.

A registered name wins over a shipped one of the same name, so the same step replaces a shipped port.

**Warning:** an entry that is not a name mapped to a class implementing the contract makes the scan throw, naming the entry. See [Troubleshooting](Troubleshooting.md).

## Reference

- [Custom conditions](Custom-conditions.md): the shipped conditions, the contract and how a name is resolved.
- [Configuration](Configuration.md): the `custom_conditions` key.
