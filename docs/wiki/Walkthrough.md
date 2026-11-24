Written against v0.2.4.

By the end of this page the dashboard will show a table of the published entries that fail their blueprints, each linked to its edit form.

It assumes the add-on is installed (see [Installation (README)](https://github.com/UAMS-Web/aether-incomplete-entries#installation)) in a Statamic 6.35 or later site whose scheduler runs (a cron entry that calls `php artisan schedule:run` every minute).

## 1. Add the widget to the dashboard

Add an entry to the `widgets` array in `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'incomplete_published_entries', 'collections' => ['pages'], 'per_page' => 25],
],
```

`collections` limits the audit to those collection handles. Leave it out to audit every collection. The other options are in [Widget](Widget.md).

## 2. Open the dashboard

Sign in to the Control Panel and open the dashboard. Before any scan has finished, the widget says that the audit runs in the background and has not completed yet, so nothing has been checked. It shows no table in this state, so a missing result never reads as a clean one.

## 3. Run the first scan

The add-on schedules the scan hourly. To see a result now, run it by hand from the site root:

```bash
php artisan cp:scan-incomplete-published-entries
```

The command prints one line per scan, such as `Scanned 732 of 732 published entries; 330 failing. Stored under incomplete_published_entries_<hash>.` A scan over a large site takes more than a minute.

## 4. Read the result

Reload the dashboard. The widget lists each failing entry with its title, collection and blueprint. The title links to the entry's edit form. Fix the entry there, and it leaves the list after the next scan.

**Note:** the list is what the last scan found. Saving an entry does not refresh it. Instead, the widget says that entries have been saved since the audit ran, and gives the time of that run, until the next scan replaces the result.

**Note:** each person sees only the failing entries they are allowed to view. A user with no permission to view any of them sees an empty table, not an error.

## Go further

- [Limiting the audit](Limiting-the-audit.md): audit fewer entries per scan.
- [Scheduling the scan yourself](Scheduling-the-scan-yourself.md): run the command on your own schedule.
- [Adding a custom condition](Adding-a-custom-condition.md): stop the audit reporting fields your editors never see.
