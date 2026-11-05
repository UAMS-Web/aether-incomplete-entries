<?php

/**
 * Pest configuration for the add-on.
 *
 * Integration tests boot a minimal Statamic through Orchestra Testbench via
 * `Tests\Support\StacheIsolatedTestCase` (which extends `Tests\TestCase` and gives each test a
 * throwaway Stache and blueprint directory); Unit tests stay on PHPUnit's own base class and boot
 * nothing. Shared helpers and custom expectations go here when the suite
 * grows them.
 */

declare(strict_types=1);

use UAMSWeb\Aether\IncompleteEntries\Tests\Support\StacheIsolatedTestCase;

pest()->extend(StacheIsolatedTestCase::class)->in('Integration');
