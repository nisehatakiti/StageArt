<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Organization;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Organization\OrganizationExternalLink;
use StageArt\Domain\Organization\OrganizationId;

final class OrganizationExternalLinkTest extends TestCase
{
    public function test_create_assigns_a_unique_id_and_stores_every_field(): void
    {
        $organizationId = OrganizationId::generate();

        $link = OrganizationExternalLink::create($organizationId, 'YouTube', 'https://youtube.com/@stageart', 2);

        $this->assertNotEmpty($link->id()->toString());
        $this->assertTrue($link->organizationId()->equals($organizationId));
        $this->assertSame('YouTube', $link->label());
        $this->assertSame('https://youtube.com/@stageart', $link->url());
        $this->assertSame(2, $link->displayOrder());
    }

    public function test_two_links_for_the_same_organization_get_different_ids(): void
    {
        $organizationId = OrganizationId::generate();

        $first = OrganizationExternalLink::create($organizationId, 'YouTube', 'https://youtube.com/@stageart', 0);
        $second = OrganizationExternalLink::create($organizationId, 'Note', 'https://note.com/stageart', 1);

        $this->assertFalse($first->id()->equals($second->id()));
    }

    public function test_update_overwrites_every_field(): void
    {
        $link = OrganizationExternalLink::create(OrganizationId::generate(), 'Old Label', 'https://old.example.com', 0);

        $link->update('New Label', 'https://new.example.com', 3);

        $this->assertSame('New Label', $link->label());
        $this->assertSame('https://new.example.com', $link->url());
        $this->assertSame(3, $link->displayOrder());
    }
}
