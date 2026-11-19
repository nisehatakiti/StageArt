<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\PublicPage;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\PublicPage\GetPublicPageAppearanceUseCase;
use StageArt\Application\PublicPage\GetPublicPageSettingsUseCase;
use StageArt\Application\PublicPage\PublicPageAccessDeniedException;
use StageArt\Application\PublicPage\PublicPageAuthorization;
use StageArt\Application\PublicPage\PublicPageOwnerNotFoundException;
use StageArt\Application\PublicPage\UpdatePublicPageHeroImageUseCase;
use StageArt\Application\PublicPage\UpdatePublicPageSettingsCommand;
use StageArt\Application\PublicPage\UpdatePublicPageSettingsUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\PublicPage\PublicPageSite;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\FakeHeroImageUploader;
use StageArt\Tests\Support\FakeHeroPresetCatalog;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryPublicPageSettingsRepository;

final class PublicPageSettingsUseCaseTest extends TestCase
{
    private const OWNER = 1;
    private const DELEGATE = 2;
    private const OUTSIDER = 3;

    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryOrganizationRepository $organizations;
    private InMemoryProjectRepository $projects;
    private InMemoryProductionRepository $productions;
    private InMemoryPublicPageSettingsRepository $settings;
    private FakeHeroImageUploader $uploader;
    private GetPublicPageSettingsUseCase $get;
    private UpdatePublicPageSettingsUseCase $update;
    private UpdatePublicPageHeroImageUseCase $uploadHero;
    private GetPublicPageAppearanceUseCase $appearance;

    private Organization $organization;
    private Production $production;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->organizations = new InMemoryOrganizationRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->settings = new InMemoryPublicPageSettingsRepository();
        $this->uploader = new FakeHeroImageUploader();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            new InMemoryProductionDelegateRepository(),
            new InMemoryParticipantRepository()
        );
        $authorization = new PublicPageAuthorization($this->organizations, $this->productions, $organizationAuthorization, $productionAuthorization);
        $catalog = new FakeHeroPresetCatalog();

        $this->get = new GetPublicPageSettingsUseCase($this->settings, $catalog, $authorization);
        $this->update = new UpdatePublicPageSettingsUseCase($this->settings, $catalog, $authorization);
        $this->uploadHero = new UpdatePublicPageHeroImageUseCase($this->settings, $catalog, $authorization, $this->uploader);
        $this->appearance = new GetPublicPageAppearanceUseCase($this->settings, $catalog);

        $this->organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($this->organization);

        $owner = Person::create(self::OWNER);
        $this->people->save($owner);
        $this->memberships->save(Membership::createOwnerMembership($this->organization->id(), $owner->id()));

        $delegate = Person::create(self::DELEGATE);
        $this->people->save($delegate);
        $this->memberships->save(Membership::create($this->organization->id(), $delegate->id(), RoleKey::organizationManager()));

        $this->people->save(Person::create(self::OUTSIDER));

        $project = Project::create($this->organization->id(), 'Season');
        $this->projects->save($project);
        $this->production = Production::create($project->id(), new ProductionName('Show'), $owner->id());
        $this->productions->save($this->production);
    }

    private function orgId(): string
    {
        return $this->organization->id()->toString();
    }

    private function productionId(): string
    {
        return $this->production->id()->toString();
    }

    /**
     * @param array<string, mixed> $theme
     * @param array<string, mixed> $hero
     */
    private function save(PublicPageSite $site, string $ownerId, array $theme, array $hero = ['enabled' => false], int $user = self::OWNER): array
    {
        return $this->update->execute(new UpdatePublicPageSettingsCommand($site, $ownerId, $user, $theme, $hero))->toArray();
    }

    public function test_a_page_never_configured_loads_the_defaults_and_the_choices(): void
    {
        $data = $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER)->toArray();

        $this->assertSame('organization', $data['site']);
        $this->assertSame(['mode' => 'preset', 'preset' => 'light_standard'], array_slice($data['theme'], 0, 2));
        $this->assertFalse($data['hero']['enabled']);
        $this->assertCount(9, $data['theme_presets']);
        $this->assertSame(['key' => 'light_standard', 'label' => 'ライト・スタンダード'], array_slice($data['theme_presets'][0], 0, 2));
        $this->assertSame(['system', 'sans', 'serif'], array_column($data['fonts'], 'key'));
        $this->assertSame(['circle-shadows', 'confetti'], array_column($data['hero_presets'], 'id'));
    }

    public function test_production_settings_offer_the_production_presets(): void
    {
        $data = $this->get->execute(PublicPageSite::production(), $this->productionId(), self::OWNER)->toArray();

        $this->assertContains('dark_drama', array_column($data['theme_presets'], 'key'));
        $this->assertSame(['sky-blue', 'sea-blue'], array_column($data['hero_presets'], 'id'));
    }

    public function test_theme_preset_is_saved_and_reloaded(): void
    {
        $this->save(PublicPageSite::organization(), $this->orgId(), ['mode' => 'preset', 'preset' => 'dark_modern']);

        $reloaded = $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER)->toArray();

        $this->assertSame('dark_modern', $reloaded['theme']['preset']);
        $this->assertSame('preset', $reloaded['theme']['mode']);
    }

    public function test_custom_colors_and_fonts_are_saved_and_reloaded(): void
    {
        $custom = ['bg' => '#101010', 'surface' => '#202020', 'text' => '#fafafa', 'primary' => '#ff8800', 'accent' => '#00aaff', 'border' => '#333333', 'heading_font' => 'serif', 'body_font' => 'sans'];
        $this->save(PublicPageSite::production(), $this->productionId(), ['mode' => 'custom', 'preset' => 'dark_drama', 'custom' => $custom]);

        $reloaded = $this->get->execute(PublicPageSite::production(), $this->productionId(), self::OWNER)->toArray();

        $this->assertSame(['mode' => 'custom', 'preset' => 'dark_drama', 'custom' => $custom], $reloaded['theme']);
    }

    public function test_organization_and_production_themes_are_independent(): void
    {
        $this->save(PublicPageSite::production(), $this->productionId(), ['preset' => 'monochrome']);
        $this->save(PublicPageSite::organization(), $this->orgId(), ['preset' => 'light_pop']);

        $organization = $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER)->toArray();
        $production = $this->get->execute(PublicPageSite::production(), $this->productionId(), self::OWNER)->toArray();

        $this->assertSame('light_pop', $organization['theme']['preset']);
        // Changing the Organization theme afterwards did not overwrite the Production's own theme.
        $this->assertSame('monochrome', $production['theme']['preset']);
    }

    public function test_two_organizations_do_not_share_settings(): void
    {
        $other = Organization::create(new OrganizationName('Other Co'));
        $this->organizations->save($other);
        $owner = $this->people->findByWordPressUserId(self::OWNER);
        $this->memberships->save(Membership::createOwnerMembership($other->id(), $owner->id()));

        $this->save(PublicPageSite::organization(), $this->orgId(), ['preset' => 'dark_theater']);

        $data = $this->get->execute(PublicPageSite::organization(), $other->id()->toString(), self::OWNER)->toArray();

        $this->assertSame('light_standard', $data['theme']['preset']);
    }

    public function test_saving_the_hero_keeps_the_theme_and_vice_versa(): void
    {
        $this->save(PublicPageSite::organization(), $this->orgId(), ['preset' => 'light_elegant'], ['enabled' => true, 'type' => 'preset', 'preset' => 'confetti']);

        $data = $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER)->toArray();

        $this->assertSame('light_elegant', $data['theme']['preset']);
        $this->assertSame(['enabled' => true, 'type' => 'preset', 'preset' => 'confetti'], array_slice($data['hero'], 0, 3));
    }

    public function test_hero_on_off_and_preset_are_saved(): void
    {
        $this->save(PublicPageSite::production(), $this->productionId(), [], ['enabled' => true, 'type' => 'preset', 'preset' => 'sea-blue', 'title' => 'Hero', 'description' => '説明']);
        $on = $this->get->execute(PublicPageSite::production(), $this->productionId(), self::OWNER)->toArray()['hero'];

        $this->save(PublicPageSite::production(), $this->productionId(), [], ['enabled' => false, 'type' => 'preset', 'preset' => 'sea-blue']);
        $off = $this->get->execute(PublicPageSite::production(), $this->productionId(), self::OWNER)->toArray()['hero'];

        $this->assertSame(['enabled' => true, 'type' => 'preset', 'preset' => 'sea-blue', 'custom_image_url' => null, 'title' => 'Hero', 'description' => '説明'], $on);
        $this->assertFalse($off['enabled']);
        $this->assertNull($off['preset']);
    }

    public function test_own_hero_image_is_uploaded_then_selected(): void
    {
        $uploaded = $this->uploadHero->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER, ['name' => 'hero.jpg'])->toArray();
        $this->assertSame('https://api.example.test/wp-content/uploads/2026/10/hero.jpg', $uploaded['hero']['custom_image_url']);
        // Uploading alone does not switch the hero on.
        $this->assertFalse($uploaded['hero']['enabled']);

        $this->save(PublicPageSite::organization(), $this->orgId(), [], ['enabled' => true, 'type' => 'custom']);
        $data = $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER)->toArray();

        $this->assertSame('custom', $data['hero']['type']);
        $this->assertSame('https://api.example.test/wp-content/uploads/2026/10/hero.jpg', $data['hero']['custom_image_url']);
    }

    public function test_own_hero_without_an_uploaded_image_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->save(PublicPageSite::organization(), $this->orgId(), [], ['enabled' => true, 'type' => 'custom']);
    }

    public function test_invalid_values_save_nothing(): void
    {
        foreach ([
            [['preset' => 'neon'], ['enabled' => false]],
            [['mode' => 'custom', 'custom' => ['bg' => 'url(x)']], ['enabled' => false]],
            [[], ['enabled' => true, 'type' => 'preset', 'preset' => '../../etc/passwd']],
            [[], ['enabled' => true, 'type' => 'preset', 'preset' => 'sky-blue']], // a production preset on an organization
        ] as [$theme, $hero]) {
            try {
                $this->save(PublicPageSite::organization(), $this->orgId(), $theme, $hero);
                $this->fail('expected the input to be rejected');
            } catch (InvalidArgumentException $expected) {
            }
        }

        $this->assertSame(0, $this->settings->saveCount);
    }

    public function test_organization_delegate_may_edit_the_organization_page(): void
    {
        $data = $this->save(PublicPageSite::organization(), $this->orgId(), ['preset' => 'light_vivid'], ['enabled' => false], self::DELEGATE);

        $this->assertSame('light_vivid', $data['theme']['preset']);
    }

    public function test_outsider_may_not_read_or_change_the_organization_page(): void
    {
        $this->expectException(PublicPageAccessDeniedException::class);

        $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OUTSIDER);
    }

    public function test_only_the_primary_manager_may_change_the_production_page(): void
    {
        $this->expectException(PublicPageAccessDeniedException::class);

        // An Organization delegate is not the Production's PrimaryManager (UpdateProductionUseCase's rule).
        $this->save(PublicPageSite::production(), $this->productionId(), ['preset' => 'light_pop'], ['enabled' => false], self::DELEGATE);
    }

    public function test_unauthorized_upload_stores_no_file(): void
    {
        try {
            $this->uploadHero->execute(PublicPageSite::production(), $this->productionId(), self::OUTSIDER, ['name' => 'x.jpg']);
            $this->fail('expected access to be denied');
        } catch (PublicPageAccessDeniedException $expected) {
        }

        $this->assertSame([], $this->uploader->uploaded);
    }

    public function test_unknown_production_is_not_found(): void
    {
        $this->expectException(PublicPageOwnerNotFoundException::class);

        $this->get->execute(PublicPageSite::production(), '00000000-0000-4000-8000-000000000000', self::OWNER);
    }

    public function test_a_page_never_configured_has_no_public_appearance(): void
    {
        // No public_page in the public response: the page keeps its earlier look.
        $this->assertNull($this->appearance->execute(PublicPageSite::organization(), $this->orgId()));
        $this->assertNull($this->appearance->execute(PublicPageSite::production(), $this->productionId()));
    }

    public function test_opening_the_settings_of_a_page_never_configured_saves_nothing(): void
    {
        $this->get->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER);
        $this->appearance->execute(PublicPageSite::organization(), $this->orgId());

        $this->assertNull($this->settings->find(PublicPageSite::organization(), $this->orgId()));
        $this->assertNull($this->appearance->execute(PublicPageSite::organization(), $this->orgId()));
    }

    public function test_whether_a_page_is_configured_is_decided_per_organization_and_production(): void
    {
        $this->save(PublicPageSite::organization(), $this->orgId(), ['mode' => 'preset', 'preset' => 'dark_theater']);

        $this->assertSame('#111114', $this->appearance->execute(PublicPageSite::organization(), $this->orgId())->toArray()['theme']['bg']);
        // The Organization's settings do not make its Production configured.
        $this->assertNull($this->appearance->execute(PublicPageSite::production(), $this->productionId()));
    }

    public function test_a_page_saved_with_the_default_preset_is_configured(): void
    {
        // Saving light_standard on purpose is a choice: it is then applied.
        $this->save(PublicPageSite::production(), $this->productionId(), ['mode' => 'preset', 'preset' => 'light_standard']);

        $data = $this->appearance->execute(PublicPageSite::production(), $this->productionId())->toArray();
        $this->assertSame('#f7f5f0', $data['theme']['bg']);
        $this->assertNull($data['hero']);
        $this->assertNull($this->appearance->execute(PublicPageSite::organization(), $this->orgId()));
    }

    public function test_public_appearance_reflects_the_saved_theme_and_hero(): void
    {
        $this->save(
            PublicPageSite::production(),
            $this->productionId(),
            ['mode' => 'custom', 'preset' => 'dark_drama', 'custom' => ['accent' => '#ff0000', 'heading_font' => 'sans']],
            ['enabled' => true, 'type' => 'preset', 'preset' => 'sky-blue', 'title' => '', 'description' => '']
        );

        $production = $this->appearance->execute(PublicPageSite::production(), $this->productionId())->toArray();
        $organization = $this->appearance->execute(PublicPageSite::organization(), $this->orgId());

        $this->assertSame('#ff0000', $production['theme']['accent']);
        $this->assertSame('sans', $production['theme']['heading_font']);
        $this->assertSame('dark', $production['theme']['mode']);
        $this->assertSame(
            ['image_url' => 'https://api.example.test/assets/hero/production/production-hero-01-sky.jpg', 'title' => '', 'description' => ''],
            $production['hero']
        );
        // The Organization page is unaffected: still not configured.
        $this->assertNull($organization);
    }

    public function test_public_appearance_hides_a_hero_that_is_off(): void
    {
        $this->uploadHero->execute(PublicPageSite::organization(), $this->orgId(), self::OWNER, ['name' => 'hero.jpg']);
        $this->save(PublicPageSite::organization(), $this->orgId(), [], ['enabled' => false, 'type' => 'custom']);

        $this->assertNull($this->appearance->execute(PublicPageSite::organization(), $this->orgId())->toArray()['hero']);
    }
}
