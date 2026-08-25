<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Follow;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Follow\OrganizationFollow;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

final class OrganizationFollowTest extends TestCase
{
    public function test_a_new_follow_is_active_with_no_unfollowed_at(): void
    {
        $follow = OrganizationFollow::create(PersonId::generate(), OrganizationId::generate());

        $this->assertTrue($follow->isActive());
        $this->assertSame(OrganizationFollow::STATUS_ACTIVE, $follow->status());
        $this->assertNull($follow->unfollowedAt());
    }

    public function test_unfollow_marks_it_inactive_and_records_unfollowed_at(): void
    {
        $follow = OrganizationFollow::create(PersonId::generate(), OrganizationId::generate());

        $follow->unfollow();

        $this->assertFalse($follow->isActive());
        $this->assertSame(OrganizationFollow::STATUS_UNFOLLOWED, $follow->status());
        $this->assertNotNull($follow->unfollowedAt());
    }

    public function test_re_following_after_an_unfollow_reactivates_it_and_clears_unfollowed_at(): void
    {
        $follow = OrganizationFollow::create(PersonId::generate(), OrganizationId::generate());
        $follow->unfollow();

        $follow->follow();

        $this->assertTrue($follow->isActive());
        $this->assertNull($follow->unfollowedAt());
    }

    public function test_identity_is_independent_of_person_and_organization(): void
    {
        $personId = PersonId::generate();
        $organizationId = OrganizationId::generate();

        $first = OrganizationFollow::create($personId, $organizationId);
        $second = OrganizationFollow::create($personId, $organizationId);

        $this->assertFalse($first->id()->equals($second->id()));
    }
}
