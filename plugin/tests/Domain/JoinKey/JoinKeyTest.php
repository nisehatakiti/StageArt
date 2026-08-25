<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\JoinKey;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\JoinKey\JoinKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class JoinKeyTest extends TestCase
{
    public function test_issued_organization_join_key_is_active_and_usable_with_an_8_character_code(): void
    {
        $joinKey = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), PersonId::generate());

        $this->assertSame(JoinKey::STATUS_ACTIVE, $joinKey->status());
        $this->assertTrue($joinKey->isUsable());
        $this->assertSame(JoinKey::TARGET_TYPE_ORGANIZATION, $joinKey->targetType());
        $this->assertSame(8, strlen($joinKey->code()));
        $this->assertSame(0, $joinKey->useCount());
    }

    public function test_issued_production_join_key_targets_production(): void
    {
        $joinKey = JoinKey::issueForProduction(ProductionId::generate()->toString(), PersonId::generate());

        $this->assertSame(JoinKey::TARGET_TYPE_PRODUCTION, $joinKey->targetType());
    }

    public function test_two_issued_keys_get_different_codes(): void
    {
        $issuer = PersonId::generate();
        $first = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), $issuer);
        $second = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), $issuer);

        $this->assertNotSame($first->code(), $second->code());
    }

    public function test_normalize_code_strips_hyphens_and_uppercases(): void
    {
        $this->assertSame('AB7K29XZ', JoinKey::normalizeCode('ab7k-29xz'));
        $this->assertSame('AB7K29XZ', JoinKey::normalizeCode('AB7K29XZ'));
        $this->assertSame('AB7K29XZ', JoinKey::normalizeCode('AB7K-29XZ'));
    }

    public function test_recording_a_use_increments_use_count(): void
    {
        $joinKey = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), PersonId::generate());

        $joinKey->recordUse();

        $this->assertSame(1, $joinKey->useCount());
        $this->assertTrue($joinKey->isUsable());
    }

    public function test_reaching_max_uses_transitions_to_exhausted_and_becomes_unusable(): void
    {
        $joinKey = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), PersonId::generate(), null, 2);

        $joinKey->recordUse();
        $this->assertTrue($joinKey->isUsable());

        $joinKey->recordUse();

        $this->assertSame(JoinKey::STATUS_EXHAUSTED, $joinKey->status());
        $this->assertFalse($joinKey->isUsable());
    }

    public function test_an_expired_key_is_not_usable_even_while_status_is_still_active(): void
    {
        $joinKey = JoinKey::issueForOrganization(
            OrganizationId::generate()->toString(),
            PersonId::generate(),
            new \DateTimeImmutable('-1 minute')
        );

        $this->assertSame(JoinKey::STATUS_ACTIVE, $joinKey->status());
        $this->assertFalse($joinKey->isUsable());
    }

    public function test_disabling_a_key_makes_it_unusable(): void
    {
        $joinKey = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), PersonId::generate());

        $joinKey->disable();

        $this->assertSame(JoinKey::STATUS_DISABLED, $joinKey->status());
        $this->assertFalse($joinKey->isUsable());
        $this->assertNotNull($joinKey->disabledAt());
    }

    public function test_an_unusable_key_cannot_record_a_use(): void
    {
        $joinKey = JoinKey::issueForOrganization(OrganizationId::generate()->toString(), PersonId::generate());
        $joinKey->disable();

        $this->expectException(\InvalidArgumentException::class);

        $joinKey->recordUse();
    }
}
