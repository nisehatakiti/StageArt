<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationCommand;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationResult;
use StageArt\Application\ParticipantInvitation\CreateParticipantInvitationUseCase;
use StageArt\Application\ParticipantInvitation\EmailKeyNotConfiguredException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationInviterRevokedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationProductionClosedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationRecentlyRejectedException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationResendLimitException;
use StageArt\Application\ParticipantInvitation\ParticipantInvitationSendLimitException;
use StageArt\Application\ParticipantInvitation\ResendParticipantInvitationCommand;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationId;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B stage 4 (design doc §5.3 / §6.2 / §6.7 / §7.1 / §9.2 / §11.3):
 * `POST /productions/{id}/participant-invitations` and
 * `POST /participant-invitations/{id}/resend` through
 * InvitationSendService.
 */
final class InvitationSendTest extends TestCase
{
    private const EMAIL = 'taro@example.com';

    private ParticipantConsentFixture $f;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
    }

    private function invite(string $email = self::EMAIL, string $type = ParticipantType::CAST, ?Production $production = null): CreateParticipantInvitationResult
    {
        return $this->f->createInvitation()->execute(new CreateParticipantInvitationCommand(
            ($production ?? $this->f->production)->id()->toString(),
            $this->f->primaryManager->wordPressUserId(),
            '招待 太郎',
            $email,
            $type,
            'internal memo'
        ));
    }

    private function resend(string $invitationId): void
    {
        $this->f->resendInvitation()->execute(new ResendParticipantInvitationCommand($invitationId, $this->f->primaryManager->wordPressUserId()));
    }

    private function stored(CreateParticipantInvitationResult $result): ParticipantInvitation
    {
        return $this->f->invitations->findById(ParticipantInvitationId::fromString($result->invitation->id));
    }

    /** A send to $email accepted $daysAgo days ago (another invitation), under key version $version. */
    private function pastSend(string $email, float $daysAgo, string $version = 'v1', ?Production $production = null): void
    {
        $key = hash_hmac('sha256', strtolower(trim($email)), $this->f->emailKeys[$version]);
        $this->f->sends->seed(ParticipantInvitationSend::accept(
            ParticipantInvitationId::generate(),
            ($production ?? $this->f->production)->id(),
            $key,
            $version,
            ParticipantInvitationSend::KIND_INITIAL,
            $this->f->primaryManager->id(),
            (new DateTimeImmutable())->sub(new DateInterval('PT' . (int) round($daysAgo * 86400) . 'S'))
        ));
    }

    private function anotherProduction(): Production
    {
        $production = Production::create($this->f->production->projectId(), new ProductionName('Other'), $this->f->primaryManager->id());
        $this->f->productions->save($production);

        return $production;
    }

    private function assertNothingWritten(int $invitations = 0): void
    {
        $this->assertCount($invitations, $this->f->invitations->findByProductionId($this->f->production->id()));
        $this->assertSame([], $this->f->sends->statuses());
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame([], $this->f->mailer->invitationEmails);
    }

    // --- create: always an invitation ----------------------------------

    public function test_a_registered_and_an_unregistered_address_get_the_same_response_and_no_lookup(): void
    {
        $this->f->person('registered@example.com');

        $registered = $this->invite('registered@example.com')->toArray();
        $unregistered = $this->invite('nobody@example.com')->toArray();

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $registered['outcome']);
        $this->assertSame($unregistered['outcome'], $registered['outcome']);
        $this->assertSame(array_keys($unregistered), array_keys($registered));
        $this->assertSame(array_keys($unregistered['invitation']), array_keys($registered['invitation']));
        $this->assertArrayNotHasKey('participant', $registered);
        $this->assertSame([], $this->f->notificationTypes(), 'No in-app notification on invitation');
        $this->assertSame([], $this->f->participants->findByProductionId($this->f->production->id()), 'Nobody added directly');

        foreach ((new ReflectionClass(CreateParticipantInvitationUseCase::class))->getConstructor()->getParameters() as $parameter) {
            $this->assertNotSame(FindPersonByEmailUseCase::class, (string) $parameter->getType(), 'No Person lookup by address');
        }
    }

    public function test_a_new_invitation_is_valid_for_7_days_and_its_send_is_counted_and_sent_without_a_token(): void
    {
        $this->f->watchProductionLock();

        $result = $this->invite();

        $invitation = $this->stored($result);
        $this->assertEqualsWithDelta((new DateTimeImmutable('+7 days'))->getTimestamp(), $invitation->expiresAt()->getTimestamp(), 5);
        $this->assertSame(['production', 'invitation', 'send'], $this->f->lockLog->order(), '§5.4');
        $this->assertSame([ParticipantInvitationSend::SENT], array_values($this->f->sends->statuses()));
        $this->assertSame($this->f->sends->all()[0]->id(), $invitation->currentSendId());
        $this->assertNotNull($invitation->lastSentAt());
        $this->assertSame([['to' => self::EMAIL, 'productionName' => 'Autumn Play']], $this->f->mailer->invitationEmails, 'No token in the mail');
        $this->assertSame([ParticipantEvent::INVITED], $this->f->eventStore->types());
        $this->assertStringNotContainsString(self::EMAIL, $this->f->everythingRecordedAndSent());
        $this->assertStringNotContainsString($invitation->tokenHash(), $this->f->everythingRecordedAndSent());
        $this->assertSame(1, preg_match('/^[0-9a-f]{64}$/', $this->f->sends->all()[0]->emailKey()));
        $this->assertNotSame(hash('sha256', self::EMAIL), $this->f->sends->all()[0]->emailKey(), 'Keyed, never a plain hash');
    }

    /** §5.3 (D5) */
    public function test_a_closed_production_refuses_create_and_resend(): void
    {
        $pending = $this->invite();
        $this->f->sends->restore([]);
        $this->f->eventStore->restore([]);
        $this->f->mailer->invitationEmails = [];

        foreach ([ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED, ProductionStatus::CANCELLED] as $status) {
            $this->f->forceProductionStatus($status);

            foreach ([fn () => $this->invite('new@example.com'), fn () => $this->resend($pending->invitation->id)] as $call) {
                try {
                    $call();
                    $this->fail("409 production_closed expected for {$status}.");
                } catch (ParticipantInvitationProductionClosedException $e) {
                    $this->addToAssertionCount(1);
                }
            }
        }

        $this->assertNothingWritten(1);
    }

    /** §5.3 (D6): 29 days after a rejection - 409 with available_at; 30 days on - allowed. */
    public function test_a_rejection_blocks_a_new_invitation_for_30_days(): void
    {
        $rejected = $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF);
        $rejectedAt = new DateTimeImmutable('-29 days');
        $rejected->reject($this->f->person('someone@example.com')->id(), null, $rejectedAt);

        try {
            $this->invite('Taro@Example.com ');
            $this->fail('409 recently_rejected expected (any type, normalised address).');
        } catch (ParticipantInvitationRecentlyRejectedException $e) {
            $this->assertEquals($rejectedAt->add(new DateInterval('P30D')), $e->availableAt());
        }

        $this->assertNothingWritten(1);

        $old = $this->f->invitation(self::EMAIL, null, ParticipantType::STAFF);
        $old->reject($this->f->person('other@example.com')->id(), null, new DateTimeImmutable('-30 days -1 minute'));
        $rejected2 = $this->f->invitations->findById($rejected->id());
        // Only the old rejection left: move the recent one out of the way.
        $this->f->invitations->restore([[$old->id()->toString() => $old], []]);

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $this->invite()->outcome);
        $this->assertNotNull($rejected2);
    }

    /** §5.3 (D6): the first send plus 5 resends; the 6th resend is 429 resend_limit. */
    public function test_an_invitation_can_be_resent_5_times(): void
    {
        $created = $this->invite();

        for ($i = 1; $i <= 5; $i++) {
            $this->resend($created->invitation->id);
        }

        $this->assertSame(5, $this->stored($created)->resendCount());
        $this->assertCount(6, $this->f->sends->all());

        try {
            $this->resend($created->invitation->id);
            $this->fail('429 resend_limit expected.');
        } catch (ParticipantInvitationResendLimitException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(6, $this->f->sends->all(), 'Nothing counted for the refused one');
        $this->assertSame(5, $this->stored($created)->resendCount());
    }

    /** §5.3: 6 sends per Production + address within 30 days, all types and invitations together. */
    public function test_the_30_day_limit_counts_every_invitation_and_type_to_the_address(): void
    {
        $this->pastSend(self::EMAIL, 20);
        $this->pastSend(self::EMAIL, 10);
        $this->pastSend(self::EMAIL, 31, 'v1'); // outside the window: not counted
        $cast = $this->invite(self::EMAIL, ParticipantType::CAST);
        $this->resend($cast->invitation->id);
        $staff = $this->invite('TARO@example.com', ParticipantType::STAFF);
        $this->resend($staff->invitation->id);

        try {
            $this->resend($staff->invitation->id);
            $this->fail('429 send_limit expected: 6 sends in 30 days.');
        } catch (ParticipantInvitationSendLimitException $e) {
            // The oldest counted send (20 days ago) leaves the window in 10 days.
            $this->assertEqualsWithDelta((new DateTimeImmutable('+10 days'))->getTimestamp(), $e->availableAt()->getTimestamp(), 5);
        }

        try {
            $this->invite(' taro@EXAMPLE.com', ParticipantType::CAST);
            $this->fail('Case / spaces must not get around the limit.');
        } catch (ParticipantInvitationSendLimitException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, $this->stored($staff)->resendCount(), 'The refused resend changed nothing');
    }

    public function test_another_productions_sends_are_not_counted(): void
    {
        $other = $this->anotherProduction();

        for ($i = 0; $i < 6; $i++) {
            $this->pastSend(self::EMAIL, 1, 'v1', $other);
        }

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_SENT, $this->invite()->outcome);
    }

    // --- §6.7 keyring ---------------------------------------------------

    public function test_no_key_configured_refuses_the_send(): void
    {
        $this->f->currentEmailKey = null;

        $this->expectException(EmailKeyNotConfiguredException::class);

        try {
            $this->invite();
        } finally {
            $this->assertNothingWritten();
        }
    }

    public function test_a_missing_old_key_used_in_the_window_refuses_the_send(): void
    {
        $this->f->emailKeys['v0'] = 'k0-0123456789abcdef0123456789abcdef';
        $this->pastSend(self::EMAIL, 5, 'v0');
        unset($this->f->emailKeys['v0']);

        try {
            $this->invite('someone-else@example.com');
            $this->fail('A version used in the window is missing: refused, never counted short.');
        } catch (EmailKeyNotConfiguredException $e) {
            $this->assertStringNotContainsString('k0-', $e->getMessage(), 'No key value in the message');
        }

        $this->assertCount(0, $this->f->invitations->findByProductionId($this->f->production->id()));
    }

    public function test_sends_under_an_older_key_version_still_count(): void
    {
        $this->f->emailKeys['v0'] = 'k0-0123456789abcdef0123456789abcdef';

        for ($i = 0; $i < 6; $i++) {
            $this->pastSend(self::EMAIL, 2, 'v0');
        }

        $this->expectException(ParticipantInvitationSendLimitException::class);
        $this->invite();
    }

    // --- §11.3 send results ----------------------------------------------

    public function test_a_failed_send_keeps_the_invitation_and_the_count(): void
    {
        $this->f->mailer->accepts = false;

        $created = $this->invite();

        $this->assertTrue($this->stored($created)->isPending());
        $this->assertSame([ParticipantInvitationSend::FAILED], array_values($this->f->sends->statuses()));
        $this->assertSame([ParticipantEvent::INVITED, ParticipantEvent::SEND_FAILED], $this->f->eventStore->types());

        $this->f->mailer->accepts = true;
        $this->f->mailer->throwNext = new RuntimeException('smtp down');
        $this->resend($created->invitation->id);

        $this->assertSame([ParticipantInvitationSend::FAILED, ParticipantInvitationSend::FAILED], array_values($this->f->sends->statuses()), 'An exception is a FAILED send too');
        $this->assertSame(1, $this->stored($created)->resendCount(), 'Still counted');
    }

    public function test_an_older_attempt_never_overwrites_a_newer_one_and_is_not_mailed(): void
    {
        $service = $this->f->sendService();
        $invitation = $this->f->invitation(self::EMAIL);
        $now = new DateTimeImmutable();

        [$older, $newer] = $this->f->transactions->run(function () use ($service, $invitation, $now): array {
            $older = $service->accept($invitation, ParticipantInvitationSend::KIND_INITIAL, $this->f->primaryManager->id(), 'Autumn Play', $now);
            $invitation->resend($now);
            $newer = $service->accept($invitation, ParticipantInvitationSend::KIND_RESEND, $this->f->primaryManager->id(), 'Autumn Play', $now);

            return [$older, $newer];
        });

        $this->assertSame(ParticipantInvitationSend::SENT, $service->deliver($newer));
        $this->assertSame(ParticipantInvitationSend::SUPERSEDED, $service->deliver($older));
        $this->assertCount(1, $this->f->mailer->invitationEmails, 'The superseded attempt sent nothing');
        $this->assertSame([$newer->sendId => ParticipantInvitationSend::SENT, $older->sendId => ParticipantInvitationSend::SUPERSEDED], array_reverse($this->f->sends->statuses(), true));
        $this->assertSame($newer->sendId, $this->f->invitations->findById($invitation->id())->currentSendId(), 'current_send_id stays on the newer one');
        $this->assertCount(2, $this->f->sends->all(), 'Both are counted');
    }

    public function test_a_result_is_recorded_once(): void
    {
        $this->f->mailer->accepts = false;
        $service = $this->f->sendService();
        $invitation = $this->f->invitation(self::EMAIL);
        $pending = $this->f->transactions->run(fn () => $service->accept($invitation, ParticipantInvitationSend::KIND_INITIAL, $this->f->primaryManager->id(), 'Autumn Play', new DateTimeImmutable()));

        $this->assertSame(ParticipantInvitationSend::FAILED, $service->deliver($pending));
        $this->assertNull($service->deliver($pending), 'Already recorded: not recorded again');
        $this->assertCount(1, $this->f->eventStore->ofType(ParticipantEvent::SEND_FAILED));
    }

    public function test_a_resend_keeps_the_token_and_moves_the_expiry(): void
    {
        $created = $this->invite();
        $before = clone $this->stored($created);

        $this->resend($created->invitation->id);

        $after = $this->stored($created);
        $this->assertSame($before->tokenHash(), $after->tokenHash());
        $this->assertGreaterThanOrEqual($before->expiresAt(), $after->expiresAt());
        $this->assertSame([ParticipantEvent::INVITED, ParticipantEvent::RESENT], $this->f->eventStore->types());

        foreach ($this->f->mailer->invitationEmails as $mail) {
            $this->assertSame(['to', 'productionName'], array_keys($mail));
        }
    }

    public function test_the_create_api_resends_a_pending_invitation_to_the_same_address_and_type(): void
    {
        $first = $this->invite();

        $second = $this->invite('  TARO@example.com');

        $this->assertSame(CreateParticipantInvitationResult::OUTCOME_INVITATION_RESENT, $second->outcome);
        $this->assertSame($first->invitation->id, $second->invitation->id);
        $this->assertCount(1, $this->f->invitations->findByProductionId($this->f->production->id()));
    }

    /** §5.3 (D3): the inviter lost permission - resend refused, nothing changed, nobody notified. */
    public function test_an_invitation_whose_inviter_lost_permission_is_not_resent(): void
    {
        $manager = $this->f->participantManager();
        $invitation = $this->f->invitation(self::EMAIL, $manager);

        foreach ($this->f->delegates->findByProductionId($this->f->production->id()) as $row) {
            $row->deactivate($this->f->primaryManager->id());
        }

        $this->expectException(ParticipantInvitationInviterRevokedException::class);

        try {
            $this->resend($invitation->id()->toString());
        } finally {
            $this->assertTrue($this->f->invitations->findById($invitation->id())->isPending());
            $this->assertSame(0, $this->f->invitations->findById($invitation->id())->resendCount());
            $this->assertSame([], $this->f->notificationTypes());
            $this->assertSame([], $this->f->sends->statuses());
        }
    }

    public function test_a_failed_send_record_write_rolls_the_new_invitation_back(): void
    {
        $this->f->sends->failNextAdd = new PersistenceException('insert into', 'wp_stageart_participant_invitation_sends', 1146);

        try {
            $this->invite();
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertNothingWritten();
        $this->assertSame(1, $this->f->transactions->rollbacks());
    }

    public function test_a_failed_count_read_refuses_the_send(): void
    {
        $this->f->sends->failNextRead = new PersistenceException('read from', 'wp_stageart_participant_invitation_sends', 1205);

        $this->expectException(PersistenceException::class);

        try {
            $this->invite();
        } finally {
            $this->assertNothingWritten();
        }
    }
}
