<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Rest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\ParticipantInvitation\CancelParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\ListParticipantInvitationsUseCase;
use StageArt\Application\ProductionDelegate\DeleteProductionDelegateUseCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\Role\RoleKey;
use StageArt\Presentation\Rest\ParticipantInvitationRestController;
use StageArt\Presentation\Rest\ParticipantRestController;
use StageArt\Presentation\Rest\ProductionDelegateRestController;
use StageArt\Presentation\Rest\RestFailureResponder;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\ParticipantConsentFixture;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PR #7 R14-B stage 4 (design doc §7.1 / §7.2): what the changed routes
 * answer - status codes and error codes - through the real UseCases on
 * the in-memory stores. No response carries an address of the invitee,
 * a token or database text.
 */
final class R14bStage4RestControllerTest extends TestCase
{
    private ParticipantConsentFixture $f;
    private ParticipantInvitationRestController $invitations;
    private ParticipantRestController $participants;
    private ProductionDelegateRestController $delegates;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Support/wordpress-rest-stubs.php';
        $GLOBALS['stageart_test_wp'] = ['current_user' => 1];

        $this->f = new ParticipantConsentFixture();
        $this->build();
    }

    /** (Re)builds the controllers from the fixture as it is now (e.g. after changing the keyring). */
    private function build(): void
    {
        $f = $this->f;
        $resolver = new PersonEmailResolver($f->people, $f->userAccounts, $f->credentials, new FakeWordPressUserLookup(), $f->notificationEmails);

        $this->invitations = new ParticipantInvitationRestController(
            $f->createInvitation(),
            $f->listInvitations(),
            $f->resendInvitation(),
            $f->cancelInvitation(),
            $f->getMine()
        );
        $this->participants = new ParticipantRestController(
            new \StageArt\Application\Participant\CreateParticipantUseCase($f->productions, $f->participants, $f->authorization, $f->transactions, $f->events),
            new GetParticipantUseCase($f->participants, $f->productions, $f->people, $f->authorization, $resolver),
            new ListParticipantsUseCase($f->participants, $f->productions, $f->people, $f->authorization, $resolver),
            $f->updateParticipant(),
            $f->cancelParticipant()
        );
        $this->delegates = new ProductionDelegateRestController(
            $f->createDelegate(),
            $f->listDelegates(),
            $f->updateDelegate(),
            new DeleteProductionDelegateUseCase($f->delegates, $f->productions, $f->authorization)
        );
    }

    /**
     * @param WP_REST_Response|WP_Error $result
     * @return array{0: int, 1: ?string, 2: mixed}
     */
    private static function answer($result): array
    {
        if ($result instanceof WP_Error) {
            $data = $result->get_error_data();

            return [(int) $data['status'], $result->get_error_code(), $data];
        }

        return [$result->get_status(), null, $result->get_data()];
    }

    private function invite(string $email = 'taro@example.com'): array
    {
        return self::answer($this->invitations->create(new WP_REST_Request([
            'id' => $this->f->production->id()->toString(),
            'name' => '招待 太郎',
            'email' => $email,
            'participant_type' => 'CAST',
        ])));
    }

    // --- invitations -----------------------------------------------------

    public function test_create_answers_201_with_the_outcome(): void
    {
        [$status, , $data] = $this->invite();

        $this->assertSame(201, $status);
        $this->assertSame('INVITATION_SENT', $data['outcome']);
        $this->assertArrayNotHasKey('participant', $data);
        $this->assertSame('INVITATION_RESENT', $this->invite()[2]['outcome']);

        // Invitation policy 2026-11-27: a verified account is registered at once.
        $member = $this->f->person('member@example.com');
        [$status, , $data] = $this->invite('member@example.com');
        $this->assertSame(201, $status);
        $this->assertSame(['outcome', 'participant_id'], array_keys($data));
        $this->assertSame('MEMBER_ADDED', $data['outcome']);
        $this->assertSame(
            [409, 'stageart_participant_already_exists'],
            array_slice($this->invite('member@example.com'), 0, 2),
            'Already an ACTIVE participant of the type: nothing changes'
        );
        $this->assertSame($member->id()->toString(), $this->f->participants->findById(\StageArt\Domain\Participant\ParticipantId::fromString($data['participant_id']))->subjectId());
    }

    public function test_create_refusals(): void
    {
        $this->f->forceProductionStatus(ProductionStatus::COMPLETED);
        $this->assertSame([409, 'stageart_participant_invitation_production_closed'], array_slice($this->invite(), 0, 2));

        $this->setUp();
        $this->f->currentEmailKey = null;
        $this->build();
        [$status, $code, $data] = $this->invite();
        $this->assertSame([500, 'stageart_email_key_not_configured'], [$status, $code]);

        // Invitation policy 2026-11-27: there is no recently_rejected refusal any more (the rejection was removed).
        $this->setUp();
        [$status, , $data] = $this->invite();
        $this->assertSame([201, 'INVITATION_SENT'], [$status, $data['outcome']]);
    }

    public function test_send_limits_are_429(): void
    {
        [, , $created] = $this->invite();
        $id = $created['invitation']['id'];

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, self::answer($this->invitations->resend(new WP_REST_Request(['id' => $id])))[0]);
        }

        $this->assertSame([429, 'stageart_participant_invitation_resend_limit'], array_slice(self::answer($this->invitations->resend(new WP_REST_Request(['id' => $id]))), 0, 2));

        [$status, $code] = $this->invite('TARO@example.com');
        $this->assertSame([429, 'stageart_participant_invitation_resend_limit'], [$status, $code], 'The create API resends the pending one: its resend limit decides first');
    }

    public function test_the_30_day_limit_answers_available_at(): void
    {
        $this->invite('a@example.com');
        $cast = $this->f->invitations->findByProductionId($this->f->production->id())[0];

        for ($i = 0; $i < 4; $i++) {
            $this->invitations->resend(new WP_REST_Request(['id' => $cast->id()->toString()]));
        }

        $this->f->invitation('a@example.com', null, ParticipantType::STAFF);
        $staff = array_values(array_filter($this->f->invitations->findByProductionId($this->f->production->id()), static fn ($i) => $i->participantType()->toString() === 'STAFF'))[0];
        $this->assertSame(200, self::answer($this->invitations->resend(new WP_REST_Request(['id' => $staff->id()->toString()])))[0], '6th send');

        [$status, $code, $data] = self::answer($this->invitations->resend(new WP_REST_Request(['id' => $staff->id()->toString()])));
        $this->assertSame([429, 'stageart_participant_invitation_send_limit'], [$status, $code]);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $data['available_at']));
    }

    /**
     * Invitation policy 2026-11-27: the invitee only opens the invitation from
     * the mail (GET /me/participant-invitations/{id}); there is no accept /
     * reject route and no own list any more.
     */
    public function test_the_invitee_opens_the_invitation_from_the_mail_and_cannot_answer_it(): void
    {
        $invitee = $this->f->person('invitee@example.com');
        $GLOBALS['stageart_test_wp']['current_user'] = $invitee->wordPressUserId();
        $mine = $this->f->invitation('invitee@example.com');
        $someoneElses = $this->f->invitation('other@example.com');

        [$status, , $data] = self::answer($this->invitations->getMine(new WP_REST_Request(['id' => $mine->id()->toString()])));
        $this->assertSame(200, $status);
        $this->assertSame(['id', 'production_id', 'production_name', 'participant_type', 'status'], array_keys($data));
        $this->assertSame('PENDING', $data['status']);
        $this->assertSame('Autumn Play', $data['production_name']);
        $this->assertStringNotContainsString('invitee@example.com', (string) json_encode($data), 'The address is not sent back');

        $this->assertSame([404, 'stageart_participant_invitation_not_found'], array_slice(self::answer($this->invitations->getMine(new WP_REST_Request(['id' => $someoneElses->id()->toString()]))), 0, 2), 'Not mine = missing');
        $this->assertSame([404, 'stageart_participant_invitation_not_found'], array_slice(self::answer($this->invitations->getMine(new WP_REST_Request(['id' => 'not-a-uuid']))), 0, 2));

        $this->f->resolve()->execute($invitee->id(), 'invitee@example.com');
        $this->assertSame('REGISTERED', self::answer($this->invitations->getMine(new WP_REST_Request(['id' => $mine->id()->toString()])))[2]['status']);

        foreach (['accept', 'reject', 'listMine'] as $removed) {
            $this->assertFalse(method_exists($this->invitations, $removed), "No {$removed} route any more");
        }

        $suspended = $this->f->person('susp@example.com', true, 'SUSPENDED');
        $GLOBALS['stageart_test_wp']['current_user'] = $suspended->wordPressUserId();
        $this->assertSame(403, self::answer($this->invitations->getMine(new WP_REST_Request(['id' => $mine->id()->toString()])))[0]);
    }

    // --- participants -------------------------------------------------------

    public function test_post_participants_refuses_person_and_organization(): void
    {
        foreach (['PERSON', 'ORGANIZATION'] as $type) {
            $answer = self::answer($this->participants->create(new WP_REST_Request([
                'id' => $this->f->production->id()->toString(),
                'subject_type' => $type,
                'subject_id' => $this->f->primaryManager->id()->toString(),
                'participant_type' => 'CAST',
            ])));
            $this->assertSame([422, 'stageart_participant_requires_invitation'], array_slice($answer, 0, 2));
        }

        $answer = self::answer($this->participants->create(new WP_REST_Request([
            'id' => $this->f->production->id()->toString(),
            'subject_type' => 'NAME_ONLY',
            'participant_type' => 'CAST',
            'display_name' => '名前だけ',
        ])));
        $this->assertSame(201, $answer[0]);
    }

    public function test_put_tells_not_sent_from_sent(): void
    {
        $member = $this->f->person('m@example.com');
        $row = $this->f->participantRow($member, ParticipantStatus::ACTIVE, ParticipantType::CAST, null, null, true);
        $row->changeRemarks('kept');
        $id = $row->id()->toString();

        $this->assertSame(200, self::answer($this->participants->update(new WP_REST_Request(['id' => $id, 'participant_type' => 'CAST'])))[0]);
        $this->assertSame('kept', $this->f->participants->findById($row->id())->remarks());
        $this->assertSame('ACTIVE', $this->f->participants->findById($row->id())->status()->toString());

        foreach ([['status' => null], ['status' => ''], ['status' => 'FOO'], ['status' => 3], ['remarks' => 5], ['remarks' => ['x']], ['participant_type' => null]] as $bad) {
            $answer = self::answer($this->participants->update(new WP_REST_Request($bad + ['id' => $id, 'participant_type' => 'CAST'])));
            $this->assertSame([422, 'stageart_participant_invalid'], array_slice($answer, 0, 2), json_encode($bad));
        }

        $this->assertSame([422, 'stageart_participant_status_change_not_allowed'], array_slice(self::answer($this->participants->update(new WP_REST_Request(['id' => $id, 'participant_type' => 'CAST', 'status' => 'DRAFT']))), 0, 2));
        $this->assertSame(200, self::answer($this->participants->update(new WP_REST_Request(['id' => $id, 'participant_type' => 'CAST', 'status' => 'INACTIVE', 'remarks' => null])))[0]);
        $this->assertNull($this->f->participants->findById($row->id())->remarks());
        $this->assertSame([422, 'stageart_participant_status_requires_consent'], array_slice(self::answer($this->participants->update(new WP_REST_Request(['id' => $id, 'participant_type' => 'CAST', 'status' => 'ACTIVE']))), 0, 2));

        $this->f->participantRow($member, ParticipantStatus::WITHDRAWN, ParticipantType::STAFF);
        $this->assertSame([409, 'stageart_participant_type_conflict'], array_slice(self::answer($this->participants->update(new WP_REST_Request(['id' => $id, 'participant_type' => 'STAFF']))), 0, 2));
    }

    public function test_delete_answers(): void
    {
        $member = $this->f->person('m@example.com');
        $pending = $this->f->participantRow($member, ParticipantStatus::PENDING);
        $active = $this->f->participantRow($member, ParticipantStatus::ACTIVE, ParticipantType::STAFF);

        $this->assertSame([409, 'stageart_participant_cancel_not_allowed'], array_slice(self::answer($this->participants->delete(new WP_REST_Request(['id' => $pending->id()->toString()]))), 0, 2));
        $this->assertSame(204, self::answer($this->participants->delete(new WP_REST_Request(['id' => $active->id()->toString()])))[0]);
        $this->assertSame(204, self::answer($this->participants->delete(new WP_REST_Request(['id' => $active->id()->toString()])))[0], 'idempotent');
    }

    // --- delegates --------------------------------------------------------

    public function test_appointment_refusals_and_the_list_fields(): void
    {
        $outsider = $this->f->person('o@example.com');
        $unconsented = $this->f->person('u@example.com');
        $this->f->participantRow($unconsented, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $legacy = $this->f->person('l@example.com');
        $this->f->participantRow($legacy, ParticipantStatus::ACTIVE, ParticipantType::CAST);
        $create = fn ($person) => self::answer($this->delegates->create(new WP_REST_Request([
            'id' => $this->f->production->id()->toString(),
            'person_id' => $person->id()->toString(),
            'role' => RoleKey::PARTICIPANT_MANAGER,
        ])));

        $this->assertSame([422, 'stageart_production_delegate_target_not_participant'], array_slice($create($outsider), 0, 2));
        // Invitation policy 2026-11-27 (user decision): an ACTIVE participant without recorded consent can be appointed.
        $this->assertSame(201, $create($unconsented)[0]);

        $this->f->delegate($legacy);
        [$status, , $list] = self::answer($this->delegates->list(new WP_REST_Request(['id' => $this->f->production->id()->toString()])));
        $this->assertSame(200, $status);
        $this->assertSame([[true, null], [true, null]], array_map(static fn (array $row): array => [$row['effective'], $row['ineffective_reason']], $list));
    }

    // --- cancel (stage 4 review fix, §5.1 / §7.1) -------------------------

    public function test_cancel_answers_200_idempotently_and_409_for_other_terminal_states(): void
    {
        $pending = $this->f->invitation('taro@example.com');
        $cancel = fn (string $id) => self::answer($this->invitations->cancel(new WP_REST_Request(['id' => $id])));

        [$status, , $data] = $cancel($pending->id()->toString());
        $this->assertSame([200, 'CANCELLED'], [$status, $data['status']]);
        $this->assertSame(200, $cancel($pending->id()->toString())[0], 'idempotent');

        $closed = $this->f->invitation('hanako@example.com');
        $closed->close(\StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason::inviterRevoked());
        $this->assertSame([409, 'stageart_participant_invitation_not_pending'], array_slice($cancel($closed->id()->toString()), 0, 2));

        $this->assertSame([404, 'stageart_participant_invitation_not_found'], array_slice($cancel(\StageArt\Domain\ParticipantInvitation\ParticipantInvitationId::generate()->toString()), 0, 2));

        $other = $this->f->invitation('jiro@example.com');
        $GLOBALS['stageart_test_wp']['current_user'] = $this->f->person('outsider@example.com')->wordPressUserId();
        $this->assertSame([403, 'stageart_participant_invitation_access_denied'], array_slice($cancel($other->id()->toString()), 0, 2));
    }

    /** R14B-4R (§7.1): the managers' list answers the added fields next to the existing ones. */
    public function test_the_managers_list_answers_the_section_7_1_fields(): void
    {
        $this->invite();
        $cancelled = $this->f->invitation('hanako@example.com');
        $cancelled->close(\StageArt\Domain\ParticipantInvitation\ParticipantInvitationCloseReason::manager(), $this->f->primaryManager->id());
        $this->f->invitations->save($cancelled);
        $list = fn (string $productionId) => self::answer($this->invitations->list(new WP_REST_Request(['id' => $productionId])));

        [$status, , $data] = $list($this->f->production->id()->toString());
        $this->assertSame(200, $status);
        $rows = array_column($data, null, 'email');
        $sent = $rows['taro@example.com'];
        $this->assertSame(['PENDING', 'PENDING', 'SENT', 0, 5], [$sent['status'], $sent['effective_status'], $sent['send_status'], $sent['resend_count'], $sent['resend_remaining']]);
        $this->assertSame(['sent_in_30_days' => 1, 'remaining_in_30_days' => 5, 'available_at' => null], $sent['send_limit']);
        $this->assertNotNull($sent['last_sent_at']);
        $this->assertArrayNotHasKey('reinvite_available_at', $sent, 'Removed with the rejection (2026-11-27)');
        $this->assertSame(['CANCELLED', 'MANAGER', null], [$rows['hanako@example.com']['effective_status'], $rows['hanako@example.com']['close_reason'], $rows['hanako@example.com']['send_status']]);
        $this->assertNotNull($rows['hanako@example.com']['closed_at']);

        $this->assertSame([404, 'stageart_production_not_found'], array_slice($list(\StageArt\Domain\Production\ProductionId::generate()->toString()), 0, 2));
        $GLOBALS['stageart_test_wp']['current_user'] = $this->f->person('outsider@example.com')->wordPressUserId();
        $this->assertSame([403, 'stageart_participant_invitation_access_denied'], array_slice($list($this->f->production->id()->toString()), 0, 2));
    }

    // --- 500 -----------------------------------------------------------------

    public function test_a_persistence_failure_is_500_without_database_text(): void
    {
        $response = RestFailureResponder::toResponse(new PersistenceException('insert into', 'wp_stageart_participant_events', 1146));

        $this->assertSame(500, $response->get_status());
        $this->assertSame('stageart_persistence_failed', $response->get_data()['code']);
        $this->assertStringNotContainsString('wp_stageart', json_encode($response->get_data()));
    }
}
