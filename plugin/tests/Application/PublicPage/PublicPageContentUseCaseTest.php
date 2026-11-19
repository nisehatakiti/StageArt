<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\PublicPage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\PublicPage\DeletePublicPageContentUseCase;
use StageArt\Application\PublicPage\GetPublicContentLayoutUseCase;
use StageArt\Application\PublicPage\GetPublicPageContentUseCase;
use StageArt\Application\PublicPage\OrganizationProductionsLookup;
use StageArt\Application\PublicPage\OrganizationPublicMembersLookup;
use StageArt\Application\PublicPage\PublicPageAccessDeniedException;
use StageArt\Application\PublicPage\PublicPageAuthorization;
use StageArt\Application\PublicPage\PublicPageContentNotFoundException;
use StageArt\Application\PublicPage\SavePublicPageContentUseCase;
use StageArt\Application\PublicPage\UpdatePublicPageContentLayoutUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Domain\PublicPage\PublicPageSite;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryContentLayoutRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryPublicPageContentRepository;

/**
 * コンテンツ tab of the Organization edit screen: saving / reading the
 * layout and the 自由コンテンツ, who may do it, and what the public page
 * then gets.
 */
final class PublicPageContentUseCaseTest extends TestCase
{
    private const OWNER = 1;
    private const DELEGATE = 2;
    private const MEMBER = 3;
    private const OUTSIDER = 4;
    private const OTHER_OWNER = 5;
    private const UNNAMED_MEMBER = 6;
    private const APPLICANT = 7;

    private InMemoryOrganizationRepository $organizations;
    private InMemoryProductionRepository $productions;
    private InMemoryContentLayoutRepository $layouts;
    private InMemoryPublicPageContentRepository $contents;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private GetPublicPageContentUseCase $get;
    private UpdatePublicPageContentLayoutUseCase $updateLayout;
    private SavePublicPageContentUseCase $save;
    private DeletePublicPageContentUseCase $delete;
    private GetPublicContentLayoutUseCase $public;

    private Organization $organization;
    private Organization $otherOrganization;
    private Production $published;
    private Production $unpublished;
    private Production $otherProduction;
    private Person $owner;
    private Person $member;
    private Person $unnamedMember;
    private Person $applicant;
    private Person $otherOwner;

    protected function setUp(): void
    {
        $people = $this->people = new InMemoryPersonRepository();
        $memberships = $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->layouts = new InMemoryContentLayoutRepository();
        $this->contents = new InMemoryPublicPageContentRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, new InMemoryProductionDelegateRepository(), new InMemoryParticipantRepository());
        $authorization = new PublicPageAuthorization($this->organizations, $this->productions, $organizationAuthorization, $productionAuthorization);
        $lookup = new OrganizationProductionsLookup($projects, $this->productions);
        $members = new OrganizationPublicMembersLookup($memberships, $people);

        $this->get = new GetPublicPageContentUseCase($this->layouts, $this->contents, $lookup, $members, $authorization);
        $this->updateLayout = new UpdatePublicPageContentLayoutUseCase($this->layouts, $this->contents, $lookup, $members, $authorization);
        $this->save = new SavePublicPageContentUseCase($this->contents, $authorization);
        $this->delete = new DeletePublicPageContentUseCase($this->contents, $authorization);
        $this->public = new GetPublicContentLayoutUseCase($this->layouts, $this->contents, $members);

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($this->organization);
        $this->otherOrganization = Organization::create(new OrganizationName('Other Co'));
        $this->organizations->save($this->otherOrganization);

        $owner = $this->owner = Person::create(self::OWNER);
        $owner->setName('座長', '花子');
        $people->save($owner);
        $memberships->save(Membership::createOwnerMembership($this->organization->id(), $owner->id()));
        $delegate = Person::create(self::DELEGATE);
        $people->save($delegate);
        $memberships->save(Membership::create($this->organization->id(), $delegate->id(), RoleKey::organizationManager()));
        $member = $this->member = Person::create(self::MEMBER);
        $member->setName('山田', '太郎');
        $people->save($member);
        $memberships->save(Membership::create($this->organization->id(), $member->id(), RoleKey::member(), '内部メモ'));
        // ACTIVE but without a name: nothing to show.
        $this->unnamedMember = Person::create(self::UNNAMED_MEMBER);
        $people->save($this->unnamedMember);
        $memberships->save(Membership::create($this->organization->id(), $this->unnamedMember->id(), RoleKey::member()));
        // Asked to join, not approved: not a member yet.
        $this->applicant = Person::create(self::APPLICANT);
        $this->applicant->setName('申請', '中');
        $people->save($this->applicant);
        $memberships->save(Membership::requestMembership($this->organization->id(), $this->applicant->id()));
        $people->save(Person::create(self::OUTSIDER));
        $otherOwner = $this->otherOwner = Person::create(self::OTHER_OWNER);
        $otherOwner->setName('他団体', '代表');
        $people->save($otherOwner);
        $memberships->save(Membership::createOwnerMembership($this->otherOrganization->id(), $otherOwner->id()));

        $project = Project::create($this->organization->id(), 'Season');
        $projects->save($project);
        $this->published = Production::create($project->id(), new ProductionName('Published Show'), $owner->id());
        $this->published->changeSlug(new ProductionSlug('published-show'));
        $this->published->activate();
        $this->productions->save($this->published);
        $this->unpublished = Production::create($project->id(), new ProductionName('Draft Show'), $owner->id());
        $this->productions->save($this->unpublished);

        $otherProject = Project::create($this->otherOrganization->id(), 'Other Season');
        $projects->save($otherProject);
        $this->otherProduction = Production::create($otherProject->id(), new ProductionName('Other Show'), $otherOwner->id());
        $this->otherProduction->changeSlug(new ProductionSlug('other-show'));
        $this->otherProduction->activate();
        $this->productions->save($this->otherProduction);
    }

    private function orgId(): string
    {
        return $this->organization->id()->toString();
    }

    private function addContent(string $title, string $status = 'publish', int $user = self::OWNER, ?string $orgId = null): array
    {
        return $this->save->execute($orgId ?? $this->orgId(), $user, null, $title, "本文1行目\r\n2行目", $status);
    }

    private static function blocks(array ...$slots): array
    {
        return [['block_id' => 'b1', 'sections' => [['section_id' => 's1', 'heading' => 'コンテンツ', 'columns' => count($slots), 'layout' => 'horizontal', 'slots' => $slots]]]];
    }

    public function test_a_page_never_configured_has_no_layout_and_offers_core_choices(): void
    {
        $result = $this->get->execute($this->orgId(), self::OWNER)->toArray();

        $this->assertNull($result['layout']);
        $this->assertSame([], $result['contents']);
        $this->assertSame(['productions', 'about'], array_column($result['choices']['system'], 'key'));
        $this->assertEqualsCanonicalizing(['Published Show', 'Draft Show'], array_column($result['choices']['productions'], 'name'));
        $this->assertSame(6, $result['choices']['max_columns']);
        $this->assertNull($this->public->execute($this->orgId(), []), 'never saved: the public page keeps its fixed order');
    }

    public function test_owner_and_delegate_save_contents_and_the_layout_in_order(): void
    {
        $news = $this->addContent('お知らせ');
        $about = $this->addContent('劇団について', 'draft', self::DELEGATE);

        $this->assertSame("本文1行目\n2行目", $news['body']);
        $this->assertSame('draft', $about['status']);

        $saved = $this->updateLayout->execute($this->orgId(), self::DELEGATE, self::blocks(
            ['type' => 'free_content', 'ref' => $about['id'], 'indent' => 0],
            ['type' => 'production', 'ref' => $this->published->id()->toString(), 'indent' => 0],
            ['type' => 'system', 'ref' => 'productions', 'indent' => 1],
        ))->toArray();

        $this->assertSame([$about['id'], $this->published->id()->toString(), 'productions'], array_column($saved['layout'][0]['sections'][0]['slots'], 'ref'));
        $this->assertSame($saved['layout'], $this->get->execute($this->orgId(), self::OWNER)->toArray()['layout']);
        $this->assertCount(2, $this->get->execute($this->orgId(), self::OWNER)->toArray()['contents']);
    }

    /** @dataProvider deniedUsers */
    public function test_members_outsiders_and_other_owners_cannot_read_or_change_anything(int $user): void
    {
        $content = $this->addContent('お知らせ');
        $calls = [
            fn () => $this->get->execute($this->orgId(), $user),
            fn () => $this->updateLayout->execute($this->orgId(), $user, []),
            fn () => $this->save->execute($this->orgId(), $user, null, 'x', '', 'publish'),
            fn () => $this->save->execute($this->orgId(), $user, $content['id'], 'x', '', 'publish'),
            fn () => $this->delete->execute($this->orgId(), $user, $content['id']),
        ];

        foreach ($calls as $i => $call) {
            try {
                $call();
                $this->fail("call {$i} was allowed");
            } catch (PublicPageAccessDeniedException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $this->layouts->saveCount);
        $this->assertSame('お知らせ', $this->get->execute($this->orgId(), self::OWNER)->toArray()['contents'][0]['title']);
    }

    /** @return array<string, array{0: int}> */
    public static function deniedUsers(): array
    {
        return ['member' => [self::MEMBER], 'outsider' => [self::OUTSIDER], 'owner of another organization' => [self::OTHER_OWNER]];
    }

    public function test_another_organizations_production_or_content_cannot_be_placed(): void
    {
        $foreign = $this->addContent('他団体', 'publish', self::OTHER_OWNER, $this->otherOrganization->id()->toString());
        $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(['type' => 'system', 'ref' => 'about', 'indent' => 0]));

        foreach ([
            ['type' => 'production', 'ref' => $this->otherProduction->id()->toString(), 'indent' => 0],
            ['type' => 'free_content', 'ref' => $foreign['id'], 'indent' => 0],
        ] as $slot) {
            try {
                $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks($slot));
                $this->fail('placed ' . $slot['type'] . ' of another organization');
            } catch (InvalidArgumentException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        // Nothing saved by the rejected calls: the earlier layout stays.
        $this->assertSame('about', $this->get->execute($this->orgId(), self::OWNER)->toArray()['layout'][0]['sections'][0]['slots'][0]['ref']);
    }

    public function test_another_organizations_content_cannot_be_edited_or_deleted_through_this_page(): void
    {
        $foreign = $this->addContent('他団体', 'publish', self::OTHER_OWNER, $this->otherOrganization->id()->toString());

        $this->expectException(PublicPageContentNotFoundException::class);
        $this->save->execute($this->orgId(), self::OWNER, $foreign['id'], '書き換え', '', 'publish');
    }

    public function test_a_foreign_content_is_not_deleted_through_this_page(): void
    {
        $foreign = $this->addContent('他団体', 'publish', self::OTHER_OWNER, $this->otherOrganization->id()->toString());

        try {
            $this->delete->execute($this->orgId(), self::OWNER, $foreign['id']);
            $this->fail('deleted a foreign content');
        } catch (PublicPageContentNotFoundException $exception) {
            $this->assertNotNull($this->contents->findById($foreign['id']));
        }
    }

    public function test_invalid_content_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->save->execute($this->orgId(), self::OWNER, null, '   ', '', 'publish');
    }

    public function test_the_public_page_gets_only_published_items_and_keeps_the_column_places(): void
    {
        $shown = $this->addContent('公開中');
        $hidden = $this->addContent('下書き', 'draft');
        $deleted = $this->addContent('削除する');

        $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(
            ['type' => 'free_content', 'ref' => $shown['id'], 'indent' => 0],
            ['type' => 'free_content', 'ref' => $hidden['id'], 'indent' => 0],
            ['type' => 'production', 'ref' => $this->published->id()->toString(), 'indent' => 0],
            ['type' => 'production', 'ref' => $this->unpublished->id()->toString(), 'indent' => 0],
            ['type' => 'free_content', 'ref' => $deleted['id'], 'indent' => 2],
            ['type' => 'heading', 'ref' => '見出し', 'indent' => 0],
        ));
        $this->delete->execute($this->orgId(), self::OWNER, $deleted['id']);

        $publicProductions = [['id' => $this->published->id()->toString(), 'name' => 'Published Show', 'slug' => 'published', 'status' => 'PLANNING']];
        $slots = $this->public->execute($this->orgId(), $publicProductions)[0]['sections'][0]['slots'];

        $this->assertSame(['free_content', 'none', 'production', 'none', 'none', 'heading'], array_column($slots, 'type'));
        $this->assertSame(['id' => $shown['id'], 'title' => '公開中', 'body' => "本文1行目\n2行目"], $slots[0]['content']);
        $this->assertSame('published', $slots[2]['production']['slug']);
        $this->assertSame(2, $slots[4]['indent']);
        $this->assertStringNotContainsString('下書き', (string) json_encode($slots, JSON_UNESCAPED_UNICODE));

        // Publishing the draft later shows it in its place, without saving the layout again.
        $this->save->execute($this->orgId(), self::OWNER, $hidden['id'], '下書き', '', 'publish');
        $this->assertSame('下書き', $this->public->execute($this->orgId(), $publicProductions)[0]['sections'][0]['slots'][1]['content']['title']);

        // The editor shows the deleted one as an empty column.
        $this->assertSame('none', $this->get->execute($this->orgId(), self::OWNER)->toArray()['layout'][0]['sections'][0]['slots'][4]['type']);
    }

    public function test_layouts_of_different_organizations_are_kept_apart(): void
    {
        $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(['type' => 'system', 'ref' => 'productions', 'indent' => 0]));

        $this->assertNull($this->get->execute($this->otherOrganization->id()->toString(), self::OTHER_OWNER)->toArray()['layout']);
        $this->assertNull($this->public->execute($this->otherOrganization->id()->toString(), []));
        $this->assertNull($this->layouts->find(PublicPageSite::production(), $this->orgId()), 'never written as a production page');
    }

    public function test_the_editor_offers_the_active_named_members_from_member_management_by_name_only(): void
    {
        $choices = $this->get->execute($this->orgId(), self::DELEGATE)->toArray()['choices']['members'];

        $this->assertEqualsCanonicalizing(['座長 花子', '山田 太郎'], array_column($choices, 'name'));
        $this->assertNotContains($this->applicant->id()->toString(), array_column($choices, 'person_id'), 'a pending applicant is not a member');
        $this->assertNotContains($this->unnamedMember->id()->toString(), array_column($choices, 'person_id'), 'no name: nothing to show');
        $this->assertNotContains($this->otherOwner->id()->toString(), array_column($choices, 'person_id'));
        foreach ($choices as $choice) {
            $this->assertSame(['person_id', 'name'], array_keys($choice), 'no role, remarks, email or join date');
        }
    }

    public function test_a_member_is_placed_by_reference_and_shown_with_the_current_name_only(): void
    {
        $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(
            ['type' => 'member', 'ref' => $this->member->id()->toString(), 'indent' => 1],
            ['type' => 'member', 'ref' => $this->owner->id()->toString(), 'indent' => 0],
        ));

        // Only the Person id is stored - nothing is copied from メンバー管理.
        $stored = $this->layouts->find(PublicPageSite::organization(), $this->orgId())->blocks()[0]['sections'][0]['slots'][0];
        $this->assertSame(['type' => 'member', 'ref' => $this->member->id()->toString(), 'indent' => 1], $stored);

        // A name changed in メンバー管理 shows up as it is now.
        $this->member->setName('山田', '次郎');
        $this->people->save($this->member);

        $slots = $this->public->execute($this->orgId(), [])[0]['sections'][0]['slots'];

        $this->assertSame(['type' => 'member', 'ref' => '', 'indent' => 1, 'member' => ['name' => '山田 次郎']], $slots[0]);
        $this->assertSame('座長 花子', $slots[1]['member']['name']);
        $json = (string) json_encode($slots, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($this->member->id()->toString(), $json, 'no Person id in the public response');
        $this->assertStringNotContainsString('内部メモ', $json);
        $this->assertStringNotContainsString('OWNER', $json);
        $this->assertStringNotContainsString('MEMBER', $json);
    }

    public function test_a_non_member_cannot_be_placed_and_nothing_is_saved(): void
    {
        $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(['type' => 'system', 'ref' => 'about', 'indent' => 0]));

        foreach ([
            'pending applicant' => $this->applicant->id()->toString(),
            'unnamed member' => $this->unnamedMember->id()->toString(),
            'member of another organization' => $this->otherOwner->id()->toString(),
            'unknown person' => '00000000-0000-4000-8000-000000000000',
            'not an id' => '1',
        ] as $label => $ref) {
            try {
                $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(['type' => 'member', 'ref' => $ref, 'indent' => 0]));
                $this->fail("placed {$label}");
            } catch (InvalidArgumentException $exception) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('about', $this->get->execute($this->orgId(), self::OWNER)->toArray()['layout'][0]['sections'][0]['slots'][0]['ref']);
    }

    public function test_a_member_who_no_longer_qualifies_becomes_an_empty_column(): void
    {
        $this->updateLayout->execute($this->orgId(), self::OWNER, self::blocks(
            ['type' => 'member', 'ref' => $this->member->id()->toString(), 'indent' => 2],
            ['type' => 'heading', 'ref' => '出演者', 'indent' => 0],
        ));

        // The membership is no longer ACTIVE (stored as メンバー管理 keeps it).
        $membership = $this->memberships->findByOrganizationAndPerson($this->organization->id(), $this->member->id());
        $this->memberships->save(Membership::reconstitute(
            $membership->id(),
            $membership->organizationId(),
            $membership->personId(),
            $membership->roleKey(),
            Membership::STATUS_REJECTED,
            $membership->createdAt(),
            $membership->joinedAt(),
            $membership->remarks()
        ));

        $slots = $this->public->execute($this->orgId(), [])[0]['sections'][0]['slots'];
        $this->assertSame([['type' => 'none', 'ref' => '', 'indent' => 2], ['type' => 'heading', 'ref' => '出演者', 'indent' => 0]], $slots);
        $this->assertSame('none', $this->get->execute($this->orgId(), self::OWNER)->toArray()['layout'][0]['sections'][0]['slots'][0]['type']);
    }
}
