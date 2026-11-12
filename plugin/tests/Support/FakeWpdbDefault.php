<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

/**
 * Returned by a FakeWpdb responder to mean "answer as if no responder
 * handled this call" (empty read, one affected row).
 */
final class FakeWpdbDefault
{
    public const VALUE = '__fake_wpdb_default__';
}
