# Aether Incomplete Entries

A Statamic add-on, part of the Aether ecosystem, that audits published entries against their blueprints and reports the ones that fail, so editors can complete them.

> **Status:** the package is being set up. The audit (a Control Panel widget, a scan command and the cache invalidation behind them) moves in from the UAMS site in a later change, so this release has no features yet. This README describes only what exists.

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
