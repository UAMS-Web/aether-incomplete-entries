# Aether Incomplete Entries

A Statamic add-on, part of the Aether ecosystem, that audits published entries against their blueprints and reports the ones that fail, so editors can complete them.

## What it does

The add-on audits every published entry against its blueprint and lists the ones that fail validation, so editors can find and complete them.

- **Dashboard widget** `incomplete_published_entries`: a table of the failing entries with a link to each edit form, paginated, and filtered by what the signed-in user may view. It never scans inside a request. It reads the result of the last scan and says so when no scan has completed, when entries have been saved since the last scan, or when an explicit `limit` truncated the audit.
- **Scan command** `cp:scan-incomplete-published-entries`: validates the entries and stores the result for the widget. The add-on schedules it hourly, in the background and without overlapping itself.
- **Cache invalidation**: saving an entry records when content changed, and keeps the last scan so the dashboard stays warm.

Enable the widget in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'incomplete_published_entries', 'collections' => ['pages'], 'per_page' => 25],
],
```

`collections` limits the audit to those handles, `limit` caps how many entries one scan checks (the default is no cap), `per_page` sets the page size and `title` overrides the heading.

### Configuration

Publish the config file with `php artisan vendor:publish --tag=aether-incomplete-entries-config`.

| Key | Default | Meaning |
| --- | --- | --- |
| `schedule.enabled` | `true` | Schedule the scan command hourly. Set it to `false` to schedule `cp:scan-incomplete-published-entries` from the application instead. |

The widget's view is `aether-incomplete-entries::widgets.incomplete_published_entries`; publish or override it from `resources/views/vendor/aether-incomplete-entries`.

The validation service behind the audit, `UAMSWeb\Aether\IncompleteEntries\Services\EntryValidation\EntryValidator`, can be resolved from the container: `validate($entry, $blueprint)` returns an `EntryValidationResult` carrying the validation errors, their attribute labels and the handles of the fields visible under the blueprint's conditions. It imports nothing from the host application.

## Requirements

PHP 8.4 or later and Statamic 6.35 or later.

## Installation

```bash
composer require uams-web/aether-incomplete-entries
```

## Development

Developing against a host application is covered in [the development guide](docs/DEVELOPMENT.md), and the Packagist and Marketplace steps in [the submission checklist](docs/SUBMISSION.md). The decisions behind the repository are in [`docs/DECISIONS.md`](docs/DECISIONS.md).

### Namespace

The add-on's code is in `UAMSWeb\Aether\IncompleteEntries\`. The convention it follows: every add-on in the Aether ecosystem sits under `UAMSWeb\Aether\`, as `UAMSWeb\Aether\<Product>` or `UAMSWeb\Aether\<Family>\<Product>` where a family groups packages. The level stays `UAMSWeb\<Product>` only for an add-on outside that ecosystem. The `autoload.psr-4` key maps the whole prefix to `src/` ([PSR-4](https://www.php-fig.org/psr/psr-4/) allows a prefix of any depth), so files under `src/` do not move when the prefix changes. A prefix has one owner or none: `UAMSWeb\Aether\Forms\Settings\` and `UAMSWeb\Aether\Forms\Multisite\` are two packages' keys, and no package maps `UAMSWeb\Aether\Forms\`. A package may own its prefix with a companion nested inside it, such as `UAMSWeb\Aether\People\ListingsManagement\` beside a package that maps `UAMSWeb\Aether\People\`. Composer picks the longest matching prefix, so a nested prefix works only when the outer package has no directory under `src/` named after the inner one.
