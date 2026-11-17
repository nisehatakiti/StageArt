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
            new ListParticipantInvitationsUseCase($f->invitations, $f->productions, $f->authorization),
            $f->resendInvitation(),
            $f->cancelInvitation(),
            $f->accept(),
            $f->reject(),
            $f->listMine()
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

    public function test_create_answers_201_with_the_outcome_only(): void
    {
        [$status, , $data] = $this->invite();

        $this->assertSame(201, $status);
        $this->assertSame('INVITATION_SENT', $data['outcome']);
        $this->assertArrayNotHasKey('participant', $data);
        $this->assertSame('INVITATION_RESENT', $this->invite()[2]['outcome']);
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

        $this->setUp();
        $rejected = $this->f->invitation('taro@example.com');
        $rejected->reject($this->f->person('x@example.com')->id(), null, new DateTimeImmutable('-1 day'));
        [$status, $code, $data] = $this->invite();
        $this->assertSame([409, 'stageart_participant_invitation_recently_rejected'], [$status, $code]);
        $this->assertArrayHasKey('available_at', $data);
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

    public function test_accept_and_reject_answer_like_the_design(): void
    {
        $invitee = $this->f->person('invitee@example.com');
        $GLOBALS['stageart_test_wp']['current_user'] = $invitee->wordPressUserId();
        $mine = $this->f->invitation('invitee@example.com');
        $someoneElses = $this->f->invitation('other@example.com');

        [$status, , $list] = self::answer($this->invitations->listMine(new WP_REST_Request()));
        $this->assertSame(200, $status);
        $this->assertSame([$mine->id()->toString()], array_column($list, 'id'));

        $this->assertSame([404, 'stageart_participant_invitation_not_found'], array_slice(self::answer($this->invitations->accept(new WP_REST_Request(['id' => $someoneElses->id()->toString()]))), 0, 2), 'Not mine = missing');
        $this->assertSame(200, self::answer($this->invitations->accept(new WP_REST_Request(['id' => $mine->id()->toString()])))[0]);
        $this->assertSame([409, 'stageart_participant_invitation_not_pending'], array_slice(self::answer($this->invitations->reject(new WP_REST_Request(['id' => $mine->id()->toString()]))), 0, 2));

        $expired = $this->f->invitation('invitee@example.com', null, ParticipantType::STAFF, new DateTimeImmutable('-1 minute'));
        $this->assertSame([410, 'stageart_participant_invitation_expired'], array_slice(self::answer($this->invitations->accept(new WP_REST_Request(['id' => $expired->id()->toString()]))), 0, 2));

        $suspended = $this->f->person('susp@example.com', true, 'SUSPENDED');
        $GLOBALS['stageart_test_wp']['current_user'] = $suspended->wordPressUserId();
        $this->assertSame(403, self::answer($this->invitations->listMine(new WP_REST_Request()))[0]);
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
        $create = fn ($person) => self::answer($this->delegates->create(new WP_REST_Request([
            'id' => $this->f->production->id()->toString(),
            'person_id' => $person->id()->toString(),
            'role' => RoleKey::PARTICIPANT_MANAGER,
        ])));

        $this->assertSame([422, 'stageart_production_delegate_target_not_participant'], array_slice($create($outsider), 0, 2));
        $this->assertSame([422, 'stageart_production_delegate_target_not_consented'], array_slice($create($unconsented), 0, 2));

        $this->f->delegate($unconsented);
        [$status, , $list] = self::answer($this->delegates->list(new WP_REST_Request(['id' => $this->f->production->id()->toString()])));
        $this->assertSame(200, $status);
        $this->assertSame([false, 'CONSENT_UNCONFIRMED'], [$list[0]['effective'], $list[0]['ineffective_reason']]);
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

    // --- 500 -----------------------------------------------------------------

    public function test_a_persistence_failure_is_500_without_database_text(): void
    {
        $response = RestFailureResponder::toResponse(new PersistenceException('insert into', 'wp_stageart_participant_events', 1146));

        $this->assertSame(500, $response->get_status());
        $this->assertSame('stageart_persistence_failed', $response->get_data()['code']);
        $this->assertStringNotContainsString('wp_stageart', json_encode($response->get_data()));
    }
}
