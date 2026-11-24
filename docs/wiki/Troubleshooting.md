Written against v0.2.4.

One entry per symptom, as you meet it: the message or behavior, its cause, and the fix.

- [The widget says the audit has not completed yet](#the-widget-says-the-audit-has-not-completed-yet)
- [The widget says entries have been saved since the audit ran](#the-widget-says-entries-have-been-saved-since-the-audit-ran)
- [The widget says only some entries were checked](#the-widget-says-only-some-entries-were-checked)
- [An entry I fixed is still listed](#an-entry-i-fixed-is-still-listed)
- [The table is empty but entries are missing fields](#the-table-is-empty-but-entries-are-missing-fields)
- [A warning says a custom field condition has no server-side port](#a-warning-says-a-custom-field-condition-has-no-server-side-port)
- [An entry fails with Published entry data could not be read](#an-entry-fails-with-published-entry-data-could-not-be-read)
- [The scan throws about custom_conditions](#the-scan-throws-about-custom_conditions)
- [A warning says an entry has a missing blueprint](#a-warning-says-an-entry-has-a-missing-blueprint)
- [Fixed](#fixed)

## The widget says the audit has not completed yet

The widget shows `This audit runs in the background and has not completed yet, so nothing has been checked.` and no table.

**Cause:** no scan has stored a result for this widget's options. This is the state on a new install, after `php artisan cache:clear`, and when the scheduler is not running. It also happens when the widget was enabled through a saved dashboard preference with options of its own that no entry in `config/statamic/cp.php` repeats, because the scan command only scans the options it finds there.

**Fix:** run `php artisan cp:scan-incomplete-published-entries` once. If the state returns, check that the site's cron entry calls `php artisan schedule:run` every minute, and that `schedule.enabled` is not `false` unless something else schedules the command. For a widget enabled through a preference, add the same options to `config/statamic/cp.php`.

## The widget says entries have been saved since the audit ran

The widget shows `Entries have been saved since this audit ran`, followed by how long ago.

**Cause:** saving an entry records the time but does not rescan, because a scan takes more than a minute. The list is the last scan's result until the next one replaces it.

**Fix:** none is needed. The next hourly scan clears the message, or run the command by hand to refresh the list now.

## The widget says only some entries were checked

The widget shows `Only N of M published entries were checked, so this list is incomplete.`

**Cause:** the widget has a `limit` option that is lower than the number of published entries.

**Fix:** raise `limit` or remove it, then run the scan again. See [Limiting the audit](Limiting-the-audit.md).

## An entry I fixed is still listed

**Cause:** the list is the last scan's result, and a save does not rescan.

**Fix:** wait for the next scan, or run `php artisan cp:scan-incomplete-published-entries`. If the entry is still listed after a fresh scan, it still fails validation: open its edit form and look for a field with an error message.

## The table is empty but entries are missing fields

**Cause:** one of three.

- The signed-in user may not view the failing entries. The widget lists only the entries Statamic's entry `view` permission allows that user to see.
- The audit checks only fields that are visible under the blueprint's display conditions. A field a condition hides is never reported.
- The widget reads a result scanned with other options, so the entries are in a collection the widget's `collections` option leaves out.

**Fix:** sign in as a user who can view the entries, check the field's display condition, and compare the widget's `collections` and `limit` with the entries you expect.

## A warning says a custom field condition has no server-side port

The log holds:

```text
EntryValidator: custom field condition has no server-side port; treating the field as hidden
```

with the `condition` and `field` it names.

**Cause:** a blueprint field uses a `custom <name>` display condition that the add-on has no PHP port for. The audit treats the field as hidden, so the field is not validated.

**Fix:** register a port under that name in the `custom_conditions` config key. See [Adding a custom condition](Adding-a-custom-condition.md).

## An entry fails with Published entry data could not be read

The entry is listed, and the log holds:

```text
EntryValidator: field preProcess failed; treating entry as failing validation
```

**Cause:** Statamic threw while reading the entry's field values, usually because a field holds data in a shape its fieldtype cannot read. The log line carries the entry id, collection and exception class.

**Fix:** open the entry in the editor, find the malformed field, and save the entry in a valid shape.

## The scan throws about custom_conditions

The scan stops with a message that begins `The aether-incomplete-entries.custom_conditions`.

**Cause:** an entry in `custom_conditions` is not a condition name mapped to an existing class that implements `UAMSWeb\Aether\IncompleteEntries\Contracts\CustomCondition`, or the whole value is not an array. The message names the entry.

**Fix:** correct the entry. The class name must be a string for a class that exists, and the class must implement the contract.

## A warning says an entry has a missing blueprint

The log holds `IncompletePublishedEntries: skipped entry with missing blueprint`.

**Cause:** an entry names a blueprint that no longer exists. The audit skips that entry, so it is neither checked nor listed.

**Fix:** restore the blueprint, or move the entry to one that exists. The warning carries the entry id and collection.

## Fixed

Problems fixed in a release stay here, with the release that fixed them, instead of being deleted. None is recorded yet.
