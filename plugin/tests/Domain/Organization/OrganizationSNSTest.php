<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationSNS;
use StageArt\Domain\Organization\OrganizationSNSService;

final class OrganizationSNSTest extends TestCase
{
    public function test_create_assigns_a_unique_id_and_stores_every_field(): void
    {
        $organizationId = OrganizationId::generate();

        $sns = OrganizationSNS::create(
            $organizationId,
            OrganizationSNSService::fromString(OrganizationSNSService::X),
            'stageart_theatre',
            'https://x.com/stageart_theatre',
            'StageArt Theatre',
            1,
            true
        );

        $this->assertNotEmpty($sns->id()->toString());
        $this->assertTrue($sns->organizationId()->equals($organizationId));
        $this->assertSame(OrganizationSNSService::X, $sns->service()->toString());
        $this->assertSame('stageart_theatre', $sns->accountIdentifier());
        $this->assertSame('https://x.com/stageart_theatre', $sns->profileUrl());
        $this->assertSame('StageArt Theatre', $sns->displayName());
        $this->assertSame(1, $sns->displayOrder());
        $this->assertTrue($sns->isVisible());
    }

    public function test_update_overwrites_every_field(): void
    {
        $sns = OrganizationSNS::create(
            OrganizationId::generate(),
            OrganizationSNSService::fromString(OrganizationSNSService::X),
            'old_handle',
            'https://x.com/old_handle',
            null,
            0,
            true
        );

        $sns->update(
            OrganizationSNSService::fromString(OrganizationSNSService::INSTAGRAM),
            'new_handle',
            'https://instagram.com/new_handle',
            'New Display Name',
            5,
            false
        );

        $this->assertSame(OrganizationSNSService::INSTAGRAM, $sns->service()->toString());
        $this->assertSame('new_handle', $sns->accountIdentifier());
        $this->assertSame('https://instagram.com/new_handle', $sns->profileUrl());
        $this->assertSame('New Display Name', $sns->displayName());
        $this->assertSame(5, $sns->displayOrder());
        $this->assertFalse($sns->isVisible());
    }

    public function test_update_touches_updated_at(): void
    {
        $sns = OrganizationSNS::create(
            OrganizationId::generate(),
            OrganizationSNSService::fromString(OrganizationSNSService::X),
            'handle',
            'https://x.com/handle',
            null,
            0,
            true
        );
        $createdAt = $sns->updatedAt();

        usleep(1000);
        $sns->update($sns->service(), $sns->accountIdentifier(), $sns->profileUrl(), $sns->displayName(), $sns->displayOrder(), $sns->isVisible());

        $this->assertGreaterThan($createdAt, $sns->updatedAt());
    }
}
