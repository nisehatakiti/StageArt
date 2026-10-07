<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\CreateOrganizationExternalLinkCommand;
use StageArt\Application\Organization\CreateOrganizationExternalLinkUseCase;
use StageArt\Application\Organization\DeleteOrganizationExternalLinkCommand;
use StageArt\Application\Organization\DeleteOrganizationExternalLinkUseCase;
use StageArt\Application\Organization\ListOrganizationExternalLinksQuery;
use StageArt\Application\Organization\ListOrganizationExternalLinksUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationExternalLinkNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationExternalLinkCommand;
use StageArt\Application\Organization\UpdateOrganizationExternalLinkUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationExternalLinkId;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationExternalLinkRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class OrganizationExternalLinkUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationExternalLinkRepository $links;
    private OrganizationAuthorizationService $authorization;
    private CreateOrganizationExternalLinkUseCase $create;
    private UpdateOrganizationExternalLinkUseCase $update;
    private DeleteOrganizationExternalLinkUseCase $delete;
    private ListOrganizationExternalLinksUseCase $list;
    private OrganizationId $organizationId;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->links = new InMemoryOrganizationExternalLinkRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->create = new CreateOrganizationExternalLinkUseCase($this->links, $this->authorization);
        $this->update = new UpdateOrganizationExternalLinkUseCase($this->links, $this->authorization);
        $this->delete = new DeleteOrganizationExternalLinkUseCase($this->links, $this->authorization);
        $this->list = new ListOrganizationExternalLinksUseCase($this->links, $this->authorization);
        $this->organizationId = OrganizationId::generate();
    }

    private function givenOwner(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::createOwnerMembership($this->organizationId, $person->id()));

        return $person;
    }

    private function givenPlainMember(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::create($this->organizationId, $person->id(), RoleKey::member()));

        return $person;
    }

    public function test_owner_can_create_an_external_link(): void
    {
        $this->givenOwner(1);

        $result = $this->create->execute(new CreateOrganizationExternalLinkCommand(
            $this->organizationId->toString(),
            1,
            'YouTube',
            'https://youtube.com/@stageart',
            0
        ));

        $this->assertSame('YouTube', $result->label);
        $this->assertSame('https://youtube.com/@stageart', $result->url);
    }

    public function test_plain_member_cannot_create_an_external_link(): void
    {
        $this->givenPlainMember(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->create->execute(new CreateOrganizationExternalLinkCommand(
            $this->organizationId->toString(),
            3,
            'YouTube',
            'https://youtube.com/@stageart'
        ));
    }

    public function test_create_rejects_an_invalid_url(): void
    {
        $this->givenOwner(1);

        $this->expectException(InvalidArgumentException::class);

        $this->create->execute(new CreateOrganizationExternalLinkCommand(
            $this->organizationId->toString(),
            1,
            'Broken',
            'not-a-url'
        ));
    }

    public function test_create_rejects_an_empty_label(): void
    {
        $this->givenOwner(1);

        $this->expectException(InvalidArgumentException::class);

        $this->create->execute(new CreateOrganizationExternalLinkCommand(
            $this->organizationId->toString(),
            1,
            '  ',
            'https://example.com'
        ));
    }

    public function test_update_overwrites_the_link(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationExternalLinkCommand(
            $this->organizationId->toString(),
            1,
            'Old Label',
            'https://old.example.com'
        ));

        $updated = $this->update->execute(new UpdateOrganizationExternalLinkCommand(
            $created->id,
            1,
            'New Label',
            'https://new.example.com',
            5
        ));

        $this->assertSame('New Label', $updated->label);
        $this->assertSame('https://new.example.com', $updated->url);
        $this->assertSame(5, $updated->displayOrder);
    }

    public function test_updating_an_unknown_id_is_not_found(): void
    {
        $this->givenOwner(1);

        $this->expectException(OrganizationExternalLinkNotFoundException::class);

        $this->update->execute(new UpdateOrganizationExternalLinkCommand(
            OrganizationExternalLinkId::generate()->toString(),
            1,
            'Label',
            'https://example.com',
            0
        ));
    }

    public function test_delete_removes_the_link(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationExternalLinkCommand(
            $this->organizationId->toString(),
            1,
            'Label',
            'https://example.com'
        ));

        $this->delete->execute(new DeleteOrganizationExternalLinkCommand($created->id, 1));

        $this->assertCount(0, $this->list->execute(new ListOrganizationExternalLinksQuery($this->organizationId->toString(), 1)));
    }

    public function test_list_returns_every_link_ordered_by_display_order(): void
    {
        $this->givenOwner(1);
        $this->create->execute(new CreateOrganizationExternalLinkCommand($this->organizationId->toString(), 1, 'Second', 'https://b.example.com', 2));
        $this->create->execute(new CreateOrganizationExternalLinkCommand($this->organizationId->toString(), 1, 'First', 'https://a.example.com', 1));

        $results = $this->list->execute(new ListOrganizationExternalLinksQuery($this->organizationId->toString(), 1));

        $this->assertCount(2, $results);
        $this->assertSame('First', $results[0]->label);
        $this->assertSame('Second', $results[1]->label);
    }
}
