Written against v0.2.4.

The add-on schedules the scan hourly, in the background and without overlapping itself. A site that wants a different time, or that schedules everything itself, turns that off and schedules the command.

## Steps

1. Publish the config file:

   ```bash
   php artisan vendor:publish --tag=aether-incomplete-entries-config
   ```

2. In `config/aether-incomplete-entries.php`, set the schedule off:

   ```php
   'schedule' => [
       'enabled' => false,
   ],
   ```

3. Schedule the command in the application, for example in `routes/console.php`:

   ```php
   use Illuminate\Support\Facades\Schedule;

   Schedule::command('cp:scan-incomplete-published-entries')
       ->dailyAt('02:00')
       ->withoutOverlapping()
       ->runInBackground();
   ```

4. Confirm the scheduler runs: `php artisan schedule:list` shows the command, and the site's cron entry calls `php artisan schedule:run` every minute.

Keep `withoutOverlapping()` and `runInBackground()`. A scan takes more than a minute on a large site, and a second scan started during it only repeats the work.

## Reference

- [Configuration](Configuration.md): `schedule.enabled`.
- [Scan command](Scan-command.md): what the command does and where it stores the result.
