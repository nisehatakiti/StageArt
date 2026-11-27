<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use PHPUnit\Framework\TestCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\ProductionDelegate\ListMyProductionDelegateRolesUseCase;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Presentation\Rest\PersonalResponseCachePolicy;
use StageArt\Presentation\Rest\ProductionDelegateRestController;
use StageArt\Tests\Support\ParticipantConsentFixture;
use WP_Error;
use WP_REST_Request;

/**
 * PR #7 R14-B stage 9 (design doc §10 「公演の画面（担当者本人）」, §4.6;
 * added with the user's approval): `GET /productions/{id}/delegates/me`
 * returns only the caller's own delegate rows, each with `effective` and
 * `ineffective_reason`.
 */
final class MyProductionDelegateRolesRestTest extends TestCase
{
    private ParticipantConsentFixture $f;
    private ProductionDelegateRestController $controller;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        $this->f = new ParticipantConsentFixture();
        $this->controller = new ProductionDelegateRestController(
            $this->f->createDelegate(),
            $this->f->listDelegates(),
            $this->f->updateDelegate(),
            new DeleteProductionDelegateUseCase($this->f->delegates, $this->f->productions, $this->f->authorization),
            new ListMyProductionDelegateRolesUseCase($this->f->delegates, $this->f->productions, $this->f->authorization)
        );
    }

    /**
     * @return mixed
     */
    private function mine(Person $as, ?string $productionId = null)
    {
        $GLOBALS['stageart_test_wp'] = ['current_user' => $as->wordPressUserId()];

        return $this->controller->listMine(new WP_REST_Request(['id' => $productionId ?? $this->f->production->id()->toString()]));
    }

    /**
     * @return array<string, array<string, mixed>> role => row
     */
    private function byRole(Person $as): array
    {
        $rows = [];
        foreach ($this->mine($as)->get_data() as $row) {
            $rows[$row['role']] = $row;
        }

        return $rows;
    }

    public function test_each_own_row_says_whether_it_is_effective_and_why_not(): void
    {
        $person = $this->f->person('delegate@example.com');
        $this->f->participantRow($person, ParticipantStatus::ACTIVE, ParticipantType::STAFF, 'INVITATION', null, true);
        $this->f->delegate($person, RoleKey::PARTICIPANT_MANAGER);
        $this->f->delegate($person, RoleKey::TICKET_MANAGER, false);

        $rows = $this->byRole($person);

        $this->assertSame(['PARTICIPANT_MANAGER', 'TICKET_MANAGER'], array_keys($rows));
        $this->assertSame(['ACTIVE', true, null], [$rows['PARTICIPANT_MANAGER']['status'], $rows['PARTICIPANT_MANAGER']['effective'], $rows['PARTICIPANT_MANAGER']['ineffective_reason']]);
        $this->assertSame(['INACTIVE', false, 'INACTIVE'], [$rows['TICKET_MANAGER']['status'], $rows['TICKET_MANAGER']['effective'], $rows['TICKET_MANAGER']['ineffective_reason']]);
        $this->assertSame(['id', 'production_id', 'role', 'status', 'effective', 'ineffective_reason'], array_keys($rows['PARTICIPANT_MANAGER']), 'No name or other Person data');
    }

    /** Invitation policy 2026-11-27 (user decision): a member without recorded consent is effective; a non-member gets the reason. */
    public function test_an_unconsented_member_is_effective_and_a_non_member_gets_the_reason(): void
    {
        $legacy = $this->f->person('legacy@example.com');
        $this->f->participantRow($legacy, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $this->f->delegate($legacy, RoleKey::REHEARSAL_MANAGER);

        $outsider = $this->f->person('former@example.com');
        $this->f->participantRow($outsider, ParticipantStatus::WITHDRAWN, ParticipantType::STAFF, 'INVITATION', null, true);
        $this->f->delegate($outsider, RoleKey::REHEARSAL_MANAGER);

        $this->assertSame([true, null], [$this->byRole($legacy)['REHEARSAL_MANAGER']['effective'], $this->byRole($legacy)['REHEARSAL_MANAGER']['ineffective_reason']]);
        $this->assertSame('NOT_ACTIVE_PARTICIPANT', $this->byRole($outsider)['REHEARSAL_MANAGER']['ineffective_reason']);
    }

    public function test_only_the_callers_own_rows_are_returned(): void
    {
        $other = $this->f->person('other@example.com');
        $this->f->participantRow($other, ParticipantStatus::ACTIVE, ParticipantType::STAFF, 'INVITATION', null, true);
        $this->f->delegate($other, RoleKey::PARTICIPANT_MANAGER);
        $nobody = $this->f->person('nobody@example.com');

        $this->assertSame([], $this->mine($nobody)->get_data(), 'Someone without rows gets an empty list, not 403');
        $this->assertSame([], $this->mine($this->f->primaryManager)->get_data(), 'The PrimaryManager sees no one else\'s rows either');
    }

    public function test_an_unknown_production_is_404(): void
    {
        $person = $this->f->person('x@example.com');

        foreach (['00000000-0000-4000-8000-000000000000', 'not-a-uuid'] as $id) {
            $answer = $this->mine($person, $id);
            $this->assertInstanceOf(WP_Error::class, $answer);
            $this->assertSame([404, 'stageart_production_not_found'], [$answer->get_error_data()['status'], $answer->get_error_code()]);
        }
    }

    public function test_the_route_is_personal_and_no_store(): void
    {
        $this->assertTrue(PersonalResponseCachePolicy::isPersonal('/stageart/v1/productions/0b7e/delegates/me'));
    }
}
