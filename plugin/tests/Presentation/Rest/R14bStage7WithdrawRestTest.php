<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Participant\CreateParticipantUseCase;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListMyParticipatingProductionsQuery;
use StageArt\Application\Participant\ListMyParticipatingProductionsUseCase;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Presentation\Rest\ParticipantRestController;
use StageArt\Presentation\Rest\RestFailureResponder;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\ParticipantConsentFixture;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PR #7 R14-B stage 7 (design doc §7.2, §4.6 D9, §5.2, §5.4; tests T25,
 * T45, T87): `POST /participants/{id}/withdraw` through the controller and
 * the real UseCase on the in-memory stores - status / error codes, who
 * may call it, the delegate roles released, the audit log, idempotency,
 * the Production status boundary, and what the participant list and the
 * Person's own participation list show afterwards.
 */
final class R14bStage7WithdrawRestTest extends TestCase
{
    private ParticipantConsentFixture $f;
    private ParticipantRestController $controller;
    private Person $member;
    private Participant $row;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';

        $this->f = new ParticipantConsentFixture();
        $resolver = new PersonEmailResolver($this->f->people, $this->f->userAccounts, $this->f->credentials, new FakeWordPressUserLookup(), $this->f->notificationEmails);
        $this->controller = new ParticipantRestController(
            new CreateParticipantUseCase($this->f->productions, $this->f->participants, $this->f->authorization, $this->f->transactions, $this->f->events),
            new GetParticipantUseCase($this->f->participants, $this->f->productions, $this->f->people, $this->f->authorization, $resolver),
            new ListParticipantsUseCase($this->f->participants, $this->f->productions, $this->f->people, $this->f->authorization, $resolver),
            $this->f->updateParticipant(),
            $this->f->cancelParticipant(),
            $this->f->withdraw()
        );

        $this->member = $this->f->person('member@example.com');
        $this->row = $this->f->participantRow($this->member, ParticipantStatus::ACTIVE, ParticipantType::CAST, 'INVITATION', null, true);
    }

    /**
     * @return array{0: int, 1: ?string, 2: mixed}
     */
    private function withdrawAs(Person $as, ?string $participantId = null): array
    {
        $GLOBALS['stageart_test_wp'] = ['current_user' => $as->wordPressUserId()];
        $result = $this->controller->withdraw(new WP_REST_Request(['id' => $participantId ?? $this->row->id()->toString()]));

        if ($result instanceof WP_Error) {
            $data = $result->get_error_data();

            return [(int) $data['status'], $result->get_error_code(), $data];
        }

        $this->assertInstanceOf(WP_REST_Response::class, $result);

        return [$result->get_status(), null, $result->get_data()];
    }

    private function statusOf(Participant $row): string
    {
        return $this->f->participants->findById($row->id())->status()->toString();
    }

    public function test_t25_the_member_withdraws_200(): void
    {
        [$status, $code, $body] = $this->withdrawAs($this->member);

        $this->assertSame(200, $status);
        $this->assertNull($code);
        $this->assertSame([
            'participant_id' => $this->row->id()->toString(),
            'production_id' => $this->f->production->id()->toString(),
            'status' => 'WITHDRAWN',
        ], $body);
        $this->assertSame(ParticipantStatus::WITHDRAWN, $this->statusOf($this->row));
        $this->assertCount(1, $this->f->participants->findByProductionId($this->f->production->id()), 'The row is kept, not deleted');

        // Audit log (§6.3): WITHDRAWN, by the Person themselves.
        $withdrawn = $this->f->eventStore->ofType(ParticipantEvent::WITHDRAWN);
        $this->assertCount(1, $withdrawn);
        $this->assertTrue($withdrawn[0]->actorPersonId()->equals($this->member->id()));
        $this->assertTrue($withdrawn[0]->participantId()->equals($this->row->id()));

        // The managers are told (§11.1); no address goes into a log or a notification.
        $this->assertContains('participant_withdrawn', $this->f->notificationTypes());
        $this->assertStringNotContainsString('member@example.com', $this->f->everythingRecordedAndSent());
    }

    /**
     * T45 / D9: every delegate row of the Person in this Production becomes
     * INACTIVE in the same operation (rows kept), and the authority stops.
     */
    public function test_t45_the_delegate_roles_are_released_and_the_authority_stops(): void
    {
        $manager = $this->f->delegate($this->member, RoleKey::PARTICIPANT_MANAGER);
        $rehearsal = $this->f->delegate($this->member, RoleKey::REHEARSAL_MANAGER);
        $this->assertTrue($this->f->authorization->canManageParticipants($this->member, $this->f->production));

        $this->assertSame(200, $this->withdrawAs($this->member)[0]);

        foreach ([$manager, $rehearsal] as $delegate) {
            $stored = $this->f->delegates->findById($delegate->id());
            $this->assertNotNull($stored, 'The delegate row is kept');
            $this->assertFalse($stored->isActive());
        }

        $this->assertCount(2, $this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED));
        foreach ($this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED) as $event) {
            $this->assertSame('PARTICIPANT_WITHDRAWN', $event->detail()['reason']);
        }

        $this->assertFalse($this->f->authorization->canManageParticipants($this->member, $this->f->production));
        $this->assertFalse($this->f->authorization->canReadProduction($this->member, $this->f->production));

        // The withdrawn delegate can no longer use the managers' APIs.
        $GLOBALS['stageart_test_wp'] = ['current_user' => $this->member->wordPressUserId()];
        $list = $this->controller->list(new WP_REST_Request(['id' => $this->f->production->id()->toString()]));
        $this->assertInstanceOf(WP_Error::class, $list);
        $this->assertSame(403, $list->get_error_data()['status']);
    }

    public function test_a_second_withdrawal_is_200_with_no_side_effects(): void
    {
        $this->f->delegate($this->member, RoleKey::PARTICIPANT_MANAGER);
        $first = $this->withdrawAs($this->member);
        $events = count($this->f->eventStore->all());
        $notifications = count($this->f->notificationTypes());

        $second = $this->withdrawAs($this->member);

        $this->assertSame($first, $second);
        $this->assertCount($events, $this->f->eventStore->all(), 'No audit event is added');
        $this->assertCount($notifications, $this->f->notificationTypes(), 'No notification is sent again');
    }

    /**
     * Only the Person themselves: another member, an outsider and even the
     * managers (PrimaryManager, PARTICIPANT_MANAGER) are 403 for this row.
     */
    public function test_someone_elses_row_is_403_for_everyone_else(): void
    {
        $other = $this->f->person('other@example.com');
        $this->f->participantRow($other, ParticipantStatus::ACTIVE, ParticipantType::STAFF, 'INVITATION', null, true);
        $outsider = $this->f->person('outsider@example.com');
        $participantManager = $this->f->participantManager();

        foreach ([$other, $outsider, $participantManager, $this->f->primaryManager] as $person) {
            [$status, $code, $data] = $this->withdrawAs($person);

            $this->assertSame([403, 'stageart_participant_access_denied'], [$status, $code]);
            $this->assertStringNotContainsString('member@example.com', (string) json_encode($data));
        }

        $this->assertSame(ParticipantStatus::ACTIVE, $this->statusOf($this->row));
        $this->assertSame([], $this->f->eventStore->ofType(ParticipantEvent::WITHDRAWN));
    }

    public function test_a_name_only_row_is_403(): void
    {
        $nameOnly = Participant::createNameOnly($this->f->production->id(), '名前だけ', ParticipantType::staff());
        $this->f->participants->save($nameOnly);

        $this->assertSame([403, 'stageart_participant_access_denied'], array_slice($this->withdrawAs($this->f->primaryManager, $nameOnly->id()->toString()), 0, 2));
    }

    public function test_a_missing_or_malformed_id_is_404(): void
    {
        $this->assertSame([404, 'stageart_participant_not_found'], array_slice($this->withdrawAs($this->member, '00000000-0000-4000-8000-000000000000'), 0, 2));
        $this->assertSame([404, 'stageart_participant_not_found'], array_slice($this->withdrawAs($this->member, 'not-a-uuid'), 0, 2));
    }

    public function test_the_primary_manager_cannot_withdraw_409(): void
    {
        $own = $this->f->participantRow($this->f->primaryManager, ParticipantStatus::ACTIVE, ParticipantType::STAFF, 'INVITATION', null, true);

        $this->assertSame([409, 'stageart_participant_withdraw_primary_manager'], array_slice($this->withdrawAs($this->f->primaryManager, $own->id()->toString()), 0, 2));
        $this->assertSame(ParticipantStatus::ACTIVE, $this->statusOf($own));
        $this->assertSame([], $this->f->eventStore->ofType(ParticipantEvent::WITHDRAWN));
        $this->assertNotContains('participant_withdrawn', $this->f->notificationTypes());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notActiveStatuses(): array
    {
        return [
            'INACTIVE' => [ParticipantStatus::INACTIVE],
            'PENDING' => [ParticipantStatus::PENDING],
            'DRAFT' => [ParticipantStatus::DRAFT],
            'CANCELLED' => [ParticipantStatus::CANCELLED],
            'REJECTED' => [ParticipantStatus::REJECTED],
        ];
    }

    /**
     * §7.2: only an ACTIVE row can be withdrawn; nothing changes otherwise.
     *
     * @dataProvider notActiveStatuses
     */
    public function test_a_row_that_is_not_active_is_409(string $rowStatus): void
    {
        $row = $this->f->participantRow($this->member, $rowStatus, ParticipantType::STAFF, null, $rowStatus === ParticipantStatus::PENDING ? new DateTimeImmutable() : null);
        $delegate = $this->f->delegate($this->member, RoleKey::REHEARSAL_MANAGER);

        $this->assertSame([409, 'stageart_participant_withdraw_not_allowed'], array_slice($this->withdrawAs($this->member, $row->id()->toString()), 0, 2));
        $this->assertSame($rowStatus, $this->statusOf($row));
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->all());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function productionStatuses(): array
    {
        return [
            'PLANNING' => ['PLANNING'],
            'ACTIVE' => ['ACTIVE'],
            'COMPLETED' => ['COMPLETED'],
            'ARCHIVED' => ['ARCHIVED'],
            'CANCELLED' => ['CANCELLED'],
        ];
    }

    /**
     * §7.2 sets no Production-status condition on withdraw: it is allowed in
     * every status, and the PrimaryManager is refused in every status.
     *
     * @dataProvider productionStatuses
     */
    public function test_the_production_status_does_not_change_the_answer(string $productionStatus): void
    {
        $this->f->forceProductionStatus($productionStatus);
        $own = $this->f->participantRow($this->f->primaryManager, ParticipantStatus::ACTIVE, ParticipantType::STAFF, 'INVITATION', null, true);

        $this->assertSame(200, $this->withdrawAs($this->member)[0]);
        $this->assertSame(409, $this->withdrawAs($this->f->primaryManager, $own->id()->toString())[0]);
    }

    /**
     * After the withdrawal the row stays in the managers' list (stage 6:
     * display_name and status only) and is gone from the Person's own list
     * of current participations (which lists ACTIVE rows only - unchanged).
     */
    public function test_the_list_and_the_persons_own_participations_afterwards(): void
    {
        $history = new ListMyParticipatingProductionsUseCase($this->f->participants, $this->f->productions, $this->f->authorization);
        $this->assertCount(1, $history->execute(new ListMyParticipatingProductionsQuery($this->member->wordPressUserId())));

        $this->withdrawAs($this->member);

        $GLOBALS['stageart_test_wp'] = ['current_user' => $this->f->primaryManager->wordPressUserId()];
        $listed = $this->controller->list(new WP_REST_Request(['id' => $this->f->production->id()->toString()]))->get_data();
        $this->assertCount(1, $listed);
        $this->assertSame('WITHDRAWN', $listed[0]['status']);
        $this->assertNull($listed[0]['subject_id']);
        $this->assertNull($listed[0]['email']);

        $this->assertSame([], $history->execute(new ListMyParticipatingProductionsQuery($this->member->wordPressUserId())));
    }

    /**
     * T87: a delegate who withdrew and later accepts a new invitation is a
     * member again, but the delegate rows stay INACTIVE until reappointed.
     */
    public function test_t87_rejoining_does_not_bring_the_delegate_role_back(): void
    {
        $delegate = $this->f->delegate($this->member, RoleKey::PARTICIPANT_MANAGER);
        $this->withdrawAs($this->member);

        $invitation = $this->f->invitation('member@example.com', null, ParticipantType::CAST);
        $this->f->accept()->execute(new \StageArt\Application\ParticipantInvitation\AcceptParticipantInvitationCommand($this->member->wordPressUserId(), $invitation->id()->toString()));

        $this->assertSame(ParticipantStatus::ACTIVE, $this->statusOf($this->row), 'The same row is ACTIVE again');
        $this->assertFalse($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertFalse($this->f->authorization->canManageParticipants($this->member, $this->f->production));
    }

    public function test_a_failed_write_is_rolled_back_and_answers_500_without_database_text(): void
    {
        $delegate = $this->f->delegate($this->member, RoleKey::PARTICIPANT_MANAGER);
        $this->f->delegates->failNextSave = new PersistenceException('update', 'wp_stageart_production_delegates', 1205);

        try {
            $this->withdrawAs($this->member);
            $this->fail('The failure goes to RestFailureResponder');
        } catch (PersistenceException $exception) {
            $response = RestFailureResponder::toResponse($exception);
            $this->assertSame(500, $response->get_status());
            $this->assertSame('stageart_persistence_failed', $response->get_data()['code']);
            $this->assertStringNotContainsString('wp_stageart', (string) json_encode($response->get_data()));
        }

        $this->assertSame(ParticipantStatus::ACTIVE, $this->statusOf($this->row));
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->all());
        $this->assertNotContains('participant_withdrawn', $this->f->notificationTypes());
    }
}
