Written against v0.2.4.

```bash
php artisan cp:scan-incomplete-published-entries
```

The command validates published entries against their blueprints and stores the result that [the widget](Widget.md) reads. It takes no arguments or options.

## What it scans

It scans once for the default widget options, and once more for each distinct `incomplete_published_entries` entry in `statamic.cp.widgets` that carries options. Each scan runs with no PHP time limit, because a scan over a large site takes more than a minute.

A widget enabled only through a saved dashboard preference, with options of its own, reads a result the command does not write. It shows its not computed state (see [Widget](Widget.md)) until its options also appear in `config/statamic/cp.php`.

## Output

One line per scan:

```text
Scanned 732 of 732 published entries; 330 failing. Stored under incomplete_published_entries_<hash>.
```

The first number is how many entries the scan checked, the second how many were published (they differ when `limit` truncated the scan), and the third how many failed. The hash is derived from the `collections` and `limit` options.

## Where the result is stored

The result goes in the application's default cache store, with no expiry, under the key shown in the output. The next scan overwrites it. Clearing the cache (`php artisan cache:clear`) removes it, and the widget then shows its not computed state until the next scan.

## Scheduling

The add-on schedules the command hourly, in the background and without overlapping itself. Set `schedule.enabled` to `false` to schedule it yourself: see [Scheduling the scan yourself](Scheduling-the-scan-yourself.md).

## Entries it skips or flags

- An entry whose blueprint no longer exists is skipped, and a warning naming the entry is logged.
- An entry whose field data cannot be read is reported as failing, and a warning is logged. Its error is `Published entry data could not be read for validation. Fix malformed fields in the entry editor.`
