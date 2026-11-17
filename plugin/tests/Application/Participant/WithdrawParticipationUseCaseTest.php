<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantConsentNotifier;
use StageArt\Application\Participant\ParticipantNotFoundException;
use StageArt\Application\Participant\ParticipantWithdrawNotAllowedException;
use StageArt\Application\Participant\ParticipantWithdrawPrimaryManagerException;
use StageArt\Application\Participant\WithdrawParticipationCommand;
use StageArt\Application\Participant\WithdrawParticipationResult;
use StageArt\Application\Shared\PersistenceException;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\ParticipantEvent\ParticipantEvent;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * R14-B §5.2 / §7.2 withdraw (D9).
 */
final class WithdrawParticipationUseCaseTest extends TestCase
{
    private ParticipantConsentFixture $f;
    private Person $member;
    private Participant $row;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->member = $this->f->person('member@example.com');
        $this->member->setName('出演', '一郎');
        $this->row = $this->f->participantRow($this->member, ParticipantStatus::ACTIVE, ParticipantType::CAST, null, null, true);
    }

    private function withdraw(?Person $as = null, ?string $participantId = null): WithdrawParticipationResult
    {
        return $this->f->withdraw()->execute(new WithdrawParticipationCommand(
            ($as ?? $this->member)->wordPressUserId(),
            $participantId ?? $this->row->id()->toString()
        ));
    }

    /**
     * @param array{personId: PersonId} $sent
     */
    private static function recipient(array $sent): string
    {
        return $sent['personId']->toString();
    }

    public function test_an_active_participant_withdraws_and_every_delegate_row_is_released(): void
    {
        $manager = $this->f->delegate($this->member, RoleKey::PARTICIPANT_MANAGER);
        $rehearsal = $this->f->delegate($this->member, RoleKey::REHEARSAL_MANAGER);
        $inactive = $this->f->delegate($this->member, RoleKey::TICKET_MANAGER, false);
        $this->f->watchProductionLock();

        $result = $this->withdraw();

        $stored = $this->f->participants->findById($this->row->id());
        $this->assertSame(ParticipantStatus::WITHDRAWN, $result->status);
        $this->assertSame(ParticipantStatus::WITHDRAWN, $stored->status()->toString());
        $this->assertSame($this->row->id()->toString(), $stored->id()->toString(), 'The row is kept (attendance / settlement stay linked)');

        foreach ([$manager, $rehearsal, $inactive] as $delegate) {
            $this->assertFalse($this->f->delegates->findById($delegate->id())->isActive());
        }

        $this->assertTrue($this->f->delegates->findById($manager->id())->updatedBy()->equals($this->member->id()));
        $this->assertSame(
            [ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::DELEGATE_DEACTIVATED, ParticipantEvent::WITHDRAWN],
            $this->f->eventStore->types(),
            'Only the rows that were ACTIVE are released and recorded'
        );

        foreach ($this->f->eventStore->ofType(ParticipantEvent::DELEGATE_DEACTIVATED) as $event) {
            $this->assertSame('PARTICIPANT_WITHDRAWN', $event->detail()['reason']);
        }

        $this->assertSame(['production', 'participant', 'delegate'], $this->f->lockLog->order());
    }

    public function test_the_primary_manager_and_effective_participant_managers_are_notified(): void
    {
        $effective = $this->f->participantManager();
        $unconsented = $this->f->person('unconsented@example.com');
        $this->f->participantRow($unconsented, ParticipantStatus::ACTIVE, ParticipantType::STAFF);
        $this->f->delegate($unconsented, RoleKey::PARTICIPANT_MANAGER);
        $rehearsalOnly = $this->f->person('rehearsal@example.com');
        $this->f->participantRow($rehearsalOnly, ParticipantStatus::ACTIVE, ParticipantType::STAFF, null, null, true);
        $this->f->delegate($rehearsalOnly, RoleKey::REHEARSAL_MANAGER);
        $this->f->delegate($this->member, RoleKey::PARTICIPANT_MANAGER);

        $this->withdraw();

        $recipients = array_map([self::class, 'recipient'], $this->f->dispatcher->dispatched());
        sort($recipients);
        $expected = [$this->f->primaryManager->id()->toString(), $effective->id()->toString()];
        sort($expected);
        $this->assertSame($expected, $recipients);
        $this->assertSame([ParticipantConsentNotifier::TYPE_PARTICIPANT_WITHDRAWN, ParticipantConsentNotifier::TYPE_PARTICIPANT_WITHDRAWN], $this->f->notificationTypes());
        $this->assertStringContainsString('出演 一郎さん', $this->f->dispatcher->dispatched()[0]['payload']['message']);
        $this->assertStringNotContainsString('member@example.com', $this->f->everythingRecordedAndSent());
    }

    public function test_a_second_withdrawal_has_no_side_effects(): void
    {
        $this->withdraw();
        $updatedAt = $this->f->participants->findById($this->row->id())->updatedAt();

        $again = $this->withdraw();

        $this->assertSame(ParticipantStatus::WITHDRAWN, $again->status);
        $this->assertSame($updatedAt, $this->f->participants->findById($this->row->id())->updatedAt());
        $this->assertSame([ParticipantEvent::WITHDRAWN], $this->f->eventStore->types());
        $this->assertCount(1, $this->f->notificationTypes());
    }

    public function test_someone_elses_participant_is_403(): void
    {
        $other = $this->f->person('other@example.com');

        $this->expectException(ParticipantAccessDeniedException::class);
        $this->withdraw($other);
    }

    public function test_a_name_only_row_is_403(): void
    {
        $nameOnly = Participant::createNameOnly($this->f->production->id(), '名前 だけ', ParticipantType::cast());
        $this->f->participants->save($nameOnly);

        $this->expectException(ParticipantAccessDeniedException::class);
        $this->withdraw(null, $nameOnly->id()->toString());
    }

    public function test_a_missing_participant_is_404(): void
    {
        $this->expectException(ParticipantNotFoundException::class);
        $this->withdraw(null, '99999999-9999-4999-8999-999999999999');
    }

    public function test_the_primary_manager_cannot_withdraw(): void
    {
        $own = $this->f->participantRow($this->f->primaryManager, ParticipantStatus::ACTIVE);

        try {
            $this->withdraw($this->f->primaryManager, $own->id()->toString());
            $this->fail('409 expected.');
        } catch (ParticipantWithdrawPrimaryManagerException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->participants->findById($own->id())->status()->toString());
    }

    public function test_only_an_active_row_can_withdraw(): void
    {
        $pending = $this->f->participantRow($this->member, ParticipantStatus::PENDING, ParticipantType::STAFF, null, new DateTimeImmutable());

        $this->expectException(ParticipantWithdrawNotAllowedException::class);
        $this->withdraw(null, $pending->id()->toString());
    }

    public function test_a_failed_delegate_write_rolls_the_withdrawal_back(): void
    {
        $delegate = $this->f->delegate($this->member);
        $this->f->delegates->failNextSave = new PersistenceException('update', 'wp_stageart_production_delegates', 1205);

        try {
            $this->withdraw();
            $this->fail('The failure must surface.');
        } catch (PersistenceException $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(ParticipantStatus::ACTIVE, $this->f->participants->findById($this->row->id())->status()->toString());
        $this->assertTrue($this->f->delegates->findById($delegate->id())->isActive());
        $this->assertSame([], $this->f->eventStore->types());
        $this->assertSame([], $this->f->notificationTypes());
    }
}
