Written against v0.2.4.

A scan checks every published entry in every collection by default. Two widget options narrow it: `collections` and `limit`.

## Steps

1. Open `config/statamic/cp.php` and find the `incomplete_published_entries` entry in `widgets`.
2. To audit some collections only, add `collections` with their handles:

   ```php
   ['type' => 'incomplete_published_entries', 'collections' => ['pages', 'news']],
   ```

3. To cap how many entries one scan checks, add `limit`:

   ```php
   ['type' => 'incomplete_published_entries', 'limit' => 500],
   ```

4. Run `php artisan cp:scan-incomplete-published-entries`. The widget reads the result stored for those options, so it shows its not computed state until this scan finishes.

## What to expect

- A capped scan checks entries in `id` order, so the same entries are checked each time.
- When a cap stops a scan early, the widget says `Only N of M published entries were checked, so this list is incomplete`, and an empty table reads `None of the published entries that were checked failed validation.` rather than a clean pass.
- `limit` of `0` checks nothing.
- Each widget entry whose options differ from the others adds a scan to each run of the command. Entries that differ only in `per_page` or `title` store their result under one key.

Leaving `limit` out is the default and the safe choice: a cap bought little time in the measurement the code records (500 entries took about 31 seconds and all 732 about 33 seconds) while leaving a third of the content unchecked.

## Reference

- [Widget](Widget.md): every widget option and the states it shows.
- [Scan command](Scan-command.md): what the command scans and stores.
