Written against v0.2.4.

The dashboard widget `incomplete_published_entries` lists the published entries that failed validation in the last scan. It never scans inside a request: it reads the result that [the scan command](Scan-command.md) stored.

## Enabling it

Add it to `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'incomplete_published_entries', 'collections' => ['pages'], 'per_page' => 25],
],
```

A bare `'incomplete_published_entries'` string entry enables it with the defaults.

## Options

| Option | Values | Default | Effect |
| --- | --- | --- | --- |
| `collections` | a list of collection handles | all collections | Audits only entries in those collections. |
| `limit` | a whole number | no cap | Caps how many published entries one scan checks, in `id` order. `0` checks none. See [Limiting the audit](Limiting-the-audit.md). |
| `per_page` | a whole number of 1 or more | `25` | Rows per page. A value below 1 falls back to 25. |
| `title` | text | `Published Entries With Validation Issues` | The widget heading. |

`collections` and `limit` decide which entries a scan checks, so they decide which stored result the widget reads: two widgets with different values for either one read different results. `per_page` and `title` change only the display.

## What it shows

The table has three columns: the entry's title (a link to its edit form), its collection and its blueprint. Paging uses the query key `incomplete_entries_page`, and the footer links Previous and Next once there is more than one page.

The widget shows one of these states above the table:

| State | Text starts | Meaning |
| --- | --- | --- |
| Not computed | `This audit runs in the background and has not completed yet` | No scan has stored a result for this widget's options. No table is shown. |
| Content changed | `Entries have been saved since this audit ran` | The result is older than the latest entry save. The list may be out of date until the next scan. |
| Truncated | `Only N of M published entries were checked` | An explicit `limit` stopped the scan early, so the list is incomplete. |

When the table is empty, the widget says `All published entries pass current validation.` After a truncated scan it says `None of the published entries that were checked failed validation.` instead, because the unchecked entries are unknown.

## Who sees which entries

The stored result is the same for everyone, and the widget filters it on every request through Statamic's own entry `view` permission. A person sees a failing entry only when they may view it. With nobody signed in, the table is empty.

## Overriding the view

The widget renders the view `aether-incomplete-entries::widgets.incomplete_published_entries`. To change it, place a file at `resources/views/vendor/aether-incomplete-entries/widgets/incomplete_published_entries.blade.php` in the site.
