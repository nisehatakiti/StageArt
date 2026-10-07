<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Organization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\CreateOrganizationSNSCommand;
use StageArt\Application\Organization\CreateOrganizationSNSUseCase;
use StageArt\Application\Organization\DeleteOrganizationSNSCommand;
use StageArt\Application\Organization\DeleteOrganizationSNSUseCase;
use StageArt\Application\Organization\ListOrganizationSNSQuery;
use StageArt\Application\Organization\ListOrganizationSNSUseCase;
use StageArt\Application\Organization\OrganizationAccessDeniedException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Organization\OrganizationSNSNotFoundException;
use StageArt\Application\Organization\UpdateOrganizationSNSCommand;
use StageArt\Application\Organization\UpdateOrganizationSNSUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationSNSId;
use StageArt\Domain\Organization\OrganizationSNSService;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationSNSRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class OrganizationSNSUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationSNSRepository $sns;
    private OrganizationAuthorizationService $authorization;
    private CreateOrganizationSNSUseCase $create;
    private UpdateOrganizationSNSUseCase $update;
    private DeleteOrganizationSNSUseCase $delete;
    private ListOrganizationSNSUseCase $list;
    private OrganizationId $organizationId;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->sns = new InMemoryOrganizationSNSRepository();
        $this->authorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->create = new CreateOrganizationSNSUseCase($this->sns, $this->authorization);
        $this->update = new UpdateOrganizationSNSUseCase($this->sns, $this->authorization);
        $this->delete = new DeleteOrganizationSNSUseCase($this->sns, $this->authorization);
        $this->list = new ListOrganizationSNSUseCase($this->sns, $this->authorization);
        $this->organizationId = OrganizationId::generate();
    }

    private function givenOwner(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::createOwnerMembership($this->organizationId, $person->id()));

        return $person;
    }

    private function givenDelegate(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::create($this->organizationId, $person->id(), RoleKey::organizationManager()));

        return $person;
    }

    private function givenPlainMember(int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->memberships->save(Membership::create($this->organizationId, $person->id(), RoleKey::member()));

        return $person;
    }

    public function test_owner_can_create_an_sns_link(): void
    {
        $this->givenOwner(1);

        $result = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'stageart_theatre',
            'https://x.com/stageart_theatre',
            'StageArt',
            0,
            true
        ));

        $this->assertSame('X', $result->service);
        $this->assertSame('stageart_theatre', $result->accountIdentifier);
    }

    public function test_delegate_can_create_an_sns_link(): void
    {
        $this->givenDelegate(2);

        $result = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            2,
            OrganizationSNSService::INSTAGRAM,
            'handle',
            'https://instagram.com/handle'
        ));

        $this->assertSame('INSTAGRAM', $result->service);
    }

    public function test_plain_member_cannot_create_an_sns_link(): void
    {
        $this->givenPlainMember(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            3,
            OrganizationSNSService::X,
            'handle',
            'https://x.com/handle'
        ));
    }

    /**
     * StageArt SNS registration simplification: X/Instagram/Facebook now
     * always derive profile_url from the account identifier and ignore
     * whatever the client sends for profile_url outright (see
     * CreateOrganizationSNSUseCase::profileUrlForService()'s own
     * docblock) - only OTHER (not creatable from the current UI, but
     * still a valid existing OrganizationSNSService) still uses a
     * client-supplied profile_url, so that is the only case real URL
     * validation still applies to.
     */
    public function test_create_rejects_an_invalid_profile_url_for_other_service(): void
    {
        $this->givenOwner(1);

        $this->expectException(InvalidArgumentException::class);

        $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::OTHER,
            'handle',
            'not-a-url'
        ));
    }

    public function test_create_with_account_identifier_only_derives_the_profile_url(): void
    {
        $this->givenOwner(1);

        $x = $this->create->execute(new CreateOrganizationSNSCommand($this->organizationId->toString(), 1, OrganizationSNSService::X, 'stageart_theatre'));
        $instagram = $this->create->execute(new CreateOrganizationSNSCommand($this->organizationId->toString(), 1, OrganizationSNSService::INSTAGRAM, 'stageart_theatre'));
        $facebook = $this->create->execute(new CreateOrganizationSNSCommand($this->organizationId->toString(), 1, OrganizationSNSService::FACEBOOK, 'stageart_theatre'));

        $this->assertSame('https://x.com/stageart_theatre', $x->profileUrl);
        $this->assertSame('https://www.instagram.com/stageart_theatre', $instagram->profileUrl);
        $this->assertSame('https://www.facebook.com/stageart_theatre', $facebook->profileUrl);
    }

    public function test_create_for_x_ignores_any_client_supplied_profile_url(): void
    {
        $this->givenOwner(1);

        $result = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'handle',
            'not-a-url-and-irrelevant'
        ));

        $this->assertSame('https://x.com/handle', $result->profileUrl);
    }

    public function test_create_for_other_service_accepts_a_valid_profile_url(): void
    {
        $this->givenOwner(1);

        $result = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::OTHER,
            'handle',
            'https://example.com/handle'
        ));

        $this->assertSame('https://example.com/handle', $result->profileUrl);
    }

    public function test_create_for_other_service_without_a_profile_url_is_null(): void
    {
        $this->givenOwner(1);

        $result = $this->create->execute(new CreateOrganizationSNSCommand($this->organizationId->toString(), 1, OrganizationSNSService::OTHER, 'handle'));

        $this->assertNull($result->profileUrl);
    }

    public function test_update_overwrites_the_link(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'old_handle',
            'https://x.com/old_handle'
        ));

        $updated = $this->update->execute(new UpdateOrganizationSNSCommand(
            $created->id,
            1,
            OrganizationSNSService::INSTAGRAM,
            'new_handle',
            'https://instagram.com/new_handle',
            'New Name',
            3,
            false
        ));

        $this->assertSame('INSTAGRAM', $updated->service);
        $this->assertSame('new_handle', $updated->accountIdentifier);
        $this->assertFalse($updated->visibility);
    }

    public function test_update_for_x_derives_the_profile_url_and_ignores_any_client_supplied_value(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationSNSCommand($this->organizationId->toString(), 1, OrganizationSNSService::OTHER, 'placeholder', 'https://example.com/placeholder'));

        $updated = $this->update->execute(new UpdateOrganizationSNSCommand(
            $created->id,
            1,
            OrganizationSNSService::X,
            'new_handle',
            'not-a-url-and-irrelevant',
            null,
            0,
            true
        ));

        $this->assertSame('https://x.com/new_handle', $updated->profileUrl);
    }

    public function test_update_rejects_an_invalid_profile_url_for_other_service(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationSNSCommand($this->organizationId->toString(), 1, OrganizationSNSService::OTHER, 'handle', 'https://example.com/handle'));

        $this->expectException(InvalidArgumentException::class);

        $this->update->execute(new UpdateOrganizationSNSCommand(
            $created->id,
            1,
            OrganizationSNSService::OTHER,
            'handle',
            'not-a-url',
            null,
            0,
            true
        ));
    }

    public function test_updating_an_unknown_id_is_not_found(): void
    {
        $this->givenOwner(1);

        $this->expectException(OrganizationSNSNotFoundException::class);

        $this->update->execute(new UpdateOrganizationSNSCommand(
            OrganizationSNSId::generate()->toString(),
            1,
            OrganizationSNSService::X,
            'handle',
            'https://x.com/handle',
            null,
            0,
            true
        ));
    }

    public function test_delete_removes_the_link(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'handle',
            'https://x.com/handle'
        ));

        $this->delete->execute(new DeleteOrganizationSNSCommand($created->id, 1));

        $this->assertCount(0, $this->list->execute(new ListOrganizationSNSQuery($this->organizationId->toString(), 1)));
    }

    public function test_plain_member_cannot_delete_a_link(): void
    {
        $this->givenOwner(1);
        $created = $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'handle',
            'https://x.com/handle'
        ));
        $this->givenPlainMember(3);

        $this->expectException(OrganizationAccessDeniedException::class);

        $this->delete->execute(new DeleteOrganizationSNSCommand($created->id, 3));
    }

    public function test_list_returns_every_link_ordered_by_display_order(): void
    {
        $this->givenOwner(1);
        $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'second',
            'https://x.com/second',
            null,
            2
        ));
        $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::INSTAGRAM,
            'first',
            'https://instagram.com/first',
            null,
            1
        ));

        $results = $this->list->execute(new ListOrganizationSNSQuery($this->organizationId->toString(), 1));

        $this->assertCount(2, $results);
        $this->assertSame('first', $results[0]->accountIdentifier);
        $this->assertSame('second', $results[1]->accountIdentifier);
    }

    public function test_list_includes_hidden_links_for_management(): void
    {
        $this->givenOwner(1);
        $this->create->execute(new CreateOrganizationSNSCommand(
            $this->organizationId->toString(),
            1,
            OrganizationSNSService::X,
            'hidden_handle',
            'https://x.com/hidden_handle',
            null,
            0,
            false
        ));

        $results = $this->list->execute(new ListOrganizationSNSQuery($this->organizationId->toString(), 1));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->visibility);
    }
}
