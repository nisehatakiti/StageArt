<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Notification\PushPreference;
use StageArt\Domain\Person\PersonId;

final class PushPreferenceTest extends TestCase
{
    public function test_create_sets_given_enabled_value(): void
    {
        $personId = PersonId::generate();

        $preference = PushPreference::create($personId, false);

        $this->assertTrue($preference->personId()->equals($personId));
        $this->assertFalse($preference->enabled());
    }

    public function test_set_enabled_toggles_and_updates_timestamp(): void
    {
        $preference = PushPreference::create(PersonId::generate(), true);
        $originalUpdatedAt = $preference->updatedAt();

        $preference->setEnabled(false);

        $this->assertFalse($preference->enabled());
        $this->assertGreaterThanOrEqual($originalUpdatedAt, $preference->updatedAt());
    }
}
