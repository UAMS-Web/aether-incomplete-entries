Written against v0.2.4.

The add-on's config file is `config/aether-incomplete-entries.php`. It works without publishing; publish it to change a value:

```bash
php artisan vendor:publish --tag=aether-incomplete-entries-config
```

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `schedule.enabled` | `true`, `false` | `true` | Schedules [the scan command](Scan-command.md) hourly, in the background and without overlapping itself. Only a value of `false` turns it off. |
| `custom_conditions` | a map of condition name to class | `[]` | Registers server-side ports of the custom field conditions your site defines in JavaScript. See [Custom conditions](Custom-conditions.md). |

The widget's own options (`collections`, `limit`, `per_page`, `title`) are set per widget in `config/statamic/cp.php`, not here: see [Widget](Widget.md).

## Example

```php
return [
    'schedule' => [
        'enabled' => false,
    ],

    'custom_conditions' => [
        'hasVideo' => \App\Conditions\HasVideo::class,
    ],
];
```
