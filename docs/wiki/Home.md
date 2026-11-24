Written against v0.2.4.

Aether Incomplete Entries is a Statamic add-on that checks every published entry against its blueprint and lists the ones that fail, so editors can find and complete them. The list appears as a Control Panel dashboard widget, and a scheduled command keeps it current.

This wiki holds the documentation: a walkthrough, task guides, the reference, and troubleshooting. The [README](https://github.com/UAMS-Web/aether-incomplete-entries#readme) keeps an overview, the requirements and installation.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/aether-incomplete-entries#installation): add the package to a Statamic install.
- [Walkthrough](Walkthrough.md): from a fresh install to a list of failing entries on the dashboard.

## Guides

- [Limiting the audit](Limiting-the-audit.md): audit some collections only, or cap how many entries one scan checks.
- [Scheduling the scan yourself](Scheduling-the-scan-yourself.md): turn off the built-in schedule and run the command from the application.
- [Adding a custom condition](Adding-a-custom-condition.md): teach the audit a display condition your site registers in JavaScript.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Widget](Widget.md): the dashboard widget, its options and the states it shows.
- [Scan command](Scan-command.md): `cp:scan-incomplete-published-entries`.
- [Configuration](Configuration.md): the config file's keys.
- [Custom conditions](Custom-conditions.md): the seven shipped conditions and the `CustomCondition` contract.
- [Validation service](Validation-service.md): `EntryValidator` and its result.

## Project

- [Project](Project.md): where the add-on is developed and how to report a problem.
