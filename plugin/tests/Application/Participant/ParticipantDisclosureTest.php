<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Participant;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Application\Participant\GetParticipantQuery;
use StageArt\Application\Participant\GetParticipantUseCase;
use StageArt\Application\Participant\ListParticipantsQuery;
use StageArt\Application\Participant\ListParticipantsUseCase;
use StageArt\Application\Participant\ParticipantAccessDeniedException;
use StageArt\Application\Participant\ParticipantResult;
use StageArt\Application\Participant\UpdateParticipantCommand;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantConsentSource;
use StageArt\Domain\Participant\ParticipantEnrollmentSource;
use StageArt\Domain\Participant\ParticipantId;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\FakeWordPressUserLookup;
use StageArt\Tests\Support\ParticipantConsentFixture;

/**
 * PR #7 R14-B stage 6 (design doc §7.3, D11 / U3; tests T24, T59): what
 * the Participant APIs return of each row, for every Production status x
 * viewer x row status.
 *
 * Expected values follow §7.3 and the decisions confirmed by the user
 * before stage 6: PENDING / DRAFT rows get no name or email; the viewer's
 * own row is returned in full; after the Production ends the consent
 * fields and the ended rows keep the PLANNING / ACTIVE rules; an ended row
 * shown to a manager also withholds subject_id and remarks.
 */
final class ParticipantDisclosureTest extends TestCase
{
    private const PRODUCTION_STATUSES = ['PLANNING', 'ACTIVE', 'COMPLETED', 'ARCHIVED', 'CANCELLED'];
    private const ROW_STATUSES = ['ACTIVE', 'INACTIVE', 'PENDING', 'DRAFT', 'CANCELLED', 'WITHDRAWN', 'REJECTED'];
    private const VIEWERS = ['PRIMARY_MANAGER', 'PROXY', 'PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'];

    private ParticipantConsentFixture $f;
    /** @var array<string, Person> */
    private array $viewers = [];
    /** @var array<string, Participant> row status => the row (one Person each) */
    private array $rows = [];
    /** @var array<string, string> row status => that Person's email */
    private array $emails = [];
    private Participant $nameOnly;

    protected function setUp(): void
    {
        $this->f = new ParticipantConsentFixture();
        $this->viewers['PRIMARY_MANAGER'] = $this->f->primaryManager;
        $this->viewers['PROXY'] = $this->delegateWith([RoleKey::PARTICIPANT_MANAGER, RoleKey::REHEARSAL_MANAGER]);
        $this->viewers['PARTICIPANT_MANAGER'] = $this->delegateWith([RoleKey::PARTICIPANT_MANAGER]);
        $this->viewers['REHEARSAL_MANAGER'] = $this->delegateWith([RoleKey::REHEARSAL_MANAGER]);

        foreach (self::ROW_STATUSES as $i => $status) {
            $email = 'member-' . strtolower($status) . '@example.com';
            $person = $this->f->person($email);
            $person->setName('Family' . $i, 'Given' . $i);
            $this->f->people->save($person);
            $this->emails[$status] = $email;
            $this->rows[$status] = $this->row($person, $status, true);
        }

        $this->nameOnly = Participant::createNameOnly($this->f->production->id(), '名前だけ 太郎', ParticipantType::staff(), 'name-only remarks');
        $this->f->participants->save($this->nameOnly);
    }

    /**
     * @param string[] $roles
     */
    private function delegateWith(array $roles): Person
    {
        $person = $this->f->person('viewer' . count($this->viewers) . '@example.com');
        $person->setName('Viewer', (string) count($this->viewers));
        $this->f->people->save($person);
        $this->row($person, 'ACTIVE', true);

        foreach ($roles as $role) {
            $this->f->delegate($person, $role);
        }

        return $person;
    }

    private function row(Person $person, string $status, bool $consented): Participant
    {
        $participant = Participant::reconstitute(
            ParticipantId::generate(),
            $this->f->production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast(),
            ParticipantStatus::fromString($status),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
            'remarks of ' . $status,
            '公演 ' . $status,
            ParticipantEnrollmentSource::fromString(ParticipantEnrollmentSource::INVITATION),
            $consented ? ParticipantConsentSource::invitation() : null,
            $consented ? new DateTimeImmutable('2026-01-03T00:00:00+00:00') : null,
            null
        );
        $this->f->participants->save($participant);

        return $participant;
    }

    private function resolver(): PersonEmailResolver
    {
        return new PersonEmailResolver($this->f->people, $this->f->userAccounts, $this->f->credentials, new FakeWordPressUserLookup(), $this->f->notificationEmails);
    }

    private function listUseCase(): ListParticipantsUseCase
    {
        return new ListParticipantsUseCase($this->f->participants, $this->f->productions, $this->f->people, $this->f->authorization, $this->resolver());
    }

    private function getUseCase(): GetParticipantUseCase
    {
        return new GetParticipantUseCase($this->f->participants, $this->f->productions, $this->f->people, $this->f->authorization, $this->resolver());
    }

    /**
     * @return array<string, ParticipantResult> by participant id
     */
    private function listAs(Person $viewer): array
    {
        $byId = [];
        foreach ($this->listUseCase()->execute(new ListParticipantsQuery($this->f->production->id()->toString(), $viewer->wordPressUserId())) as $result) {
            $byId[$result->id] = $result;
        }

        return $byId;
    }

    /**
     * §7.3 written out per viewer, independently of ParticipantDisclosurePolicy.
     *
     * @return array{names: bool, email: bool, consent: bool, identity: bool}|null null = row not returned
     */
    private static function expected(string $viewer, string $productionStatus, string $rowStatus): ?array
    {
        $closed = in_array($productionStatus, ['COMPLETED', 'ARCHIVED', 'CANCELLED'], true);
        $enrolled = in_array($rowStatus, ['ACTIVE', 'INACTIVE'], true);
        $ended = in_array($rowStatus, ['CANCELLED', 'WITHDRAWN', 'REJECTED'], true);

        if ($viewer === 'REHEARSAL_MANAGER') {
            if ($ended) {
                return null;
            }

            return ['names' => $enrolled && ! $closed, 'email' => false, 'consent' => false, 'identity' => true];
        }

        if ($ended) {
            return ['names' => false, 'email' => false, 'consent' => false, 'identity' => false];
        }

        $emailAfterClose = $viewer === 'PRIMARY_MANAGER';

        return ['names' => $enrolled, 'email' => $enrolled && (! $closed || $emailAfterClose), 'consent' => true, 'identity' => true];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function productionStatusesAndViewers(): array
    {
        $cases = [];
        foreach (self::PRODUCTION_STATUSES as $productionStatus) {
            foreach (self::VIEWERS as $viewer) {
                $cases["{$productionStatus} / {$viewer}"] = [$productionStatus, $viewer];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider productionStatusesAndViewers
     */
    public function test_each_row_returns_only_what_section_7_3_allows(string $productionStatus, string $viewer): void
    {
        $this->f->forceProductionStatus($productionStatus);
        $listed = $this->listAs($this->viewers[$viewer]);

        foreach (self::ROW_STATUSES as $i => $rowStatus) {
            $participant = $this->rows[$rowStatus];
            $expected = self::expected($viewer, $productionStatus, $rowStatus);
            $label = "{$productionStatus} / {$viewer} / {$rowStatus}";

            if ($expected === null) {
                $this->assertArrayNotHasKey($participant->id()->toString(), $listed, "{$label}: the row is not returned");
                continue;
            }

            $this->assertArrayHasKey($participant->id()->toString(), $listed, "{$label}: the row is returned");
            $row = $listed[$participant->id()->toString()]->toArray();

            $this->assertSame($rowStatus, $row['status'], $label);
            $this->assertSame('公演 ' . $rowStatus, $row['display_name'], "{$label}: display_name");
            $this->assertSame('PERSON', $row['subject_type'], $label);
            $this->assertSame('CAST', $row['participant_type'], $label);
            $this->assertSame($expected['identity'] ? $participant->subjectId() : null, $row['subject_id'], "{$label}: subject_id");
            $this->assertSame($expected['identity'] ? 'remarks of ' . $rowStatus : null, $row['remarks'], "{$label}: remarks");
            $this->assertSame($expected['names'] ? 'Family' . $i : null, $row['person_family_name'], "{$label}: family name");
            $this->assertSame($expected['names'] ? 'Given' . $i : null, $row['person_given_name'], "{$label}: given name");
            $this->assertSame($expected['email'] ? $this->emails[$rowStatus] : null, $row['email'], "{$label}: email");
            $this->assertSame($expected['consent'] ? 'INVITATION' : null, $row['enrollment_source'], "{$label}: enrollment_source");
            $this->assertSame($expected['consent'] ? 'CONFIRMED' : null, $row['consent_status'], "{$label}: consent_status");
            $this->assertSame($expected['consent'] ? '2026-01-03T00:00:00+00:00' : null, $row['consented_at'], "{$label}: consented_at");
        }

        // NAME_ONLY: no Person behind it; managers see its consent fields as NOT_APPLICABLE.
        $nameOnly = $listed[$this->nameOnly->id()->toString()]->toArray();
        $this->assertSame('名前だけ 太郎', $nameOnly['display_name']);
        $this->assertNull($nameOnly['email']);
        $this->assertSame($viewer === 'REHEARSAL_MANAGER' ? null : 'NOT_APPLICABLE', $nameOnly['consent_status']);
        $this->assertSame($viewer === 'REHEARSAL_MANAGER' ? null : 'MANAGER_RECORD', $nameOnly['enrollment_source']);
    }

    /**
     * @dataProvider productionStatusesAndViewers
     */
    public function test_no_withheld_email_or_name_appears_anywhere_in_the_response(string $productionStatus, string $viewer): void
    {
        $this->f->forceProductionStatus($productionStatus);
        $json = (string) json_encode(array_map(static fn (ParticipantResult $r): array => $r->toArray(), array_values($this->listAs($this->viewers[$viewer]))));

        foreach (self::ROW_STATUSES as $i => $rowStatus) {
            $expected = self::expected($viewer, $productionStatus, $rowStatus);

            if ($expected === null || ! $expected['email']) {
                $this->assertStringNotContainsString($this->emails[$rowStatus], $json, "{$productionStatus} / {$viewer} / {$rowStatus}: email");
            }

            if ($expected === null || ! $expected['names']) {
                $this->assertStringNotContainsString('Family' . $i, $json, "{$productionStatus} / {$viewer} / {$rowStatus}: family name");
            }

            if ($expected === null || ! $expected['identity']) {
                $this->assertStringNotContainsString($this->rows[$rowStatus]->subjectId(), $json, "{$productionStatus} / {$viewer} / {$rowStatus}: person id");
            }
        }
    }

    /**
     * T24: the read-only REHEARSAL_MANAGER gets no email and no ended rows.
     */
    public function test_t24_the_rehearsal_manager_gets_no_email_and_no_ended_rows(): void
    {
        $listed = $this->listAs($this->viewers['REHEARSAL_MANAGER']);
        $others = array_filter($listed, fn (ParticipantResult $r): bool => $r->subjectId !== $this->viewers['REHEARSAL_MANAGER']->id()->toString());

        foreach ($others as $result) {
            $this->assertNull($result->email);
            $this->assertNotContains($result->status, ['CANCELLED', 'WITHDRAWN', 'REJECTED']);
        }

        $this->assertCount(4 + 1 + 2, $others, 'ACTIVE / INACTIVE / PENDING / DRAFT, the NAME_ONLY row and the two other delegates');
    }

    /**
     * T59: after the Production ends only the PrimaryManager gets the email,
     * and every row is still there (nothing is deleted - D11).
     */
    public function test_t59_after_the_production_ends_only_the_primary_manager_gets_the_email(): void
    {
        $countBefore = count($this->f->participants->findByProductionId($this->f->production->id()));
        $this->f->forceProductionStatus('COMPLETED');

        $active = $this->rows['ACTIVE']->id()->toString();
        $this->assertSame($this->emails['ACTIVE'], $this->listAs($this->viewers['PRIMARY_MANAGER'])[$active]->email);
        $this->assertNull($this->listAs($this->viewers['PROXY'])[$active]->email);
        $this->assertNull($this->listAs($this->viewers['PARTICIPANT_MANAGER'])[$active]->email);
        $this->assertNull($this->listAs($this->viewers['REHEARSAL_MANAGER'])[$active]->email);

        $this->assertCount($countBefore, $this->f->participants->findByProductionId($this->f->production->id()));
        $this->assertCount($countBefore, $this->listAs($this->viewers['PRIMARY_MANAGER']), 'The PrimaryManager still lists every row');
    }

    /**
     * 本人 (confirmed): the viewer's own row is returned in full, even to
     * the read-only REHEARSAL_MANAGER after the Production ends.
     */
    public function test_the_viewers_own_row_is_returned_in_full(): void
    {
        foreach (self::PRODUCTION_STATUSES as $productionStatus) {
            $this->f->forceProductionStatus($productionStatus);

            foreach (['PROXY', 'PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'] as $viewer) {
                $me = $this->viewers[$viewer];
                $own = array_values(array_filter($this->listAs($me), static fn (ParticipantResult $r): bool => $r->subjectId === $me->id()->toString()));

                $this->assertCount(1, $own, "{$productionStatus} / {$viewer}");
                $this->assertSame('Viewer', $own[0]->personFamilyName, "{$productionStatus} / {$viewer}");
                $this->assertNotNull($own[0]->email, "{$productionStatus} / {$viewer}");
                $this->assertSame('CONFIRMED', $own[0]->consentStatus, "{$productionStatus} / {$viewer}");
            }
        }
    }

    public function test_consent_status_is_unconfirmed_for_a_person_without_recorded_consent(): void
    {
        $legacy = $this->f->person('legacy@example.com');
        $row = $this->row($legacy, 'ACTIVE', false);

        $result = $this->listAs($this->f->primaryManager)[$row->id()->toString()];

        $this->assertSame('UNCONFIRMED', $result->consentStatus);
        $this->assertNull($result->consentedAt);
        $this->assertSame('legacy@example.com', $result->email);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function productionStatuses(): array
    {
        return array_combine(self::PRODUCTION_STATUSES, array_map(static fn (string $s): array => [$s], self::PRODUCTION_STATUSES));
    }

    /**
     * GET /participants/{id} (managers only) returns a row as the list does.
     *
     * @dataProvider productionStatuses
     */
    public function test_get_returns_a_row_as_the_list_does(string $productionStatus): void
    {
        $this->f->forceProductionStatus($productionStatus);

        foreach (['PRIMARY_MANAGER', 'PROXY', 'PARTICIPANT_MANAGER'] as $viewer) {
            $listed = $this->listAs($this->viewers[$viewer]);

            foreach (self::ROW_STATUSES as $rowStatus) {
                $id = $this->rows[$rowStatus]->id()->toString();
                $fetched = $this->getUseCase()->execute(new GetParticipantQuery($id, $this->viewers[$viewer]->wordPressUserId()));

                $this->assertSame($listed[$id]->toArray(), $fetched->toArray(), "{$productionStatus} / {$viewer} / {$rowStatus}");
            }
        }
    }

    public function test_the_rehearsal_manager_and_outsiders_cannot_get_or_list(): void
    {
        $id = $this->rows['ACTIVE']->id()->toString();
        $outsider = $this->f->person('outsider@example.com');
        $inactiveDelegate = $this->f->person('inactive-delegate@example.com');
        $this->row($inactiveDelegate, 'ACTIVE', true);
        $this->f->delegate($inactiveDelegate, RoleKey::PARTICIPANT_MANAGER, false);
        $plainMember = $this->f->person('plain-member@example.com');
        $this->row($plainMember, 'ACTIVE', true);

        foreach ([$this->viewers['REHEARSAL_MANAGER'], $outsider, $inactiveDelegate, $plainMember] as $person) {
            try {
                $this->getUseCase()->execute(new GetParticipantQuery($id, $person->wordPressUserId()));
                $this->fail('GET must be refused');
            } catch (ParticipantAccessDeniedException $expected) {
                $this->addToAssertionCount(1);
            }
        }

        foreach ([$outsider, $inactiveDelegate, $plainMember] as $person) {
            try {
                $this->listAs($person);
                $this->fail('The list must be refused');
            } catch (ParticipantAccessDeniedException $expected) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * PUT /participants/{id}'s response is cut down the same way.
     */
    public function test_the_update_response_follows_the_same_rules(): void
    {
        $id = $this->rows['ACTIVE']->id()->toString();
        $manager = $this->viewers['PARTICIPANT_MANAGER'];

        $open = $this->f->updateParticipant()->execute(new UpdateParticipantCommand($id, $manager->wordPressUserId(), 'CAST'));
        $this->assertSame($this->emails['ACTIVE'], $open->email);
        $this->assertSame('CONFIRMED', $open->consentStatus);

        $this->f->forceProductionStatus('COMPLETED');
        $closed = $this->f->updateParticipant()->execute(new UpdateParticipantCommand($id, $manager->wordPressUserId(), 'CAST'));
        $this->assertNull($closed->email, 'After the Production ends only the PrimaryManager gets the email');
        $this->assertSame('Family0', $closed->personFamilyName);

        $byPrimaryManager = $this->f->updateParticipant()->execute(new UpdateParticipantCommand($id, $this->f->primaryManager->wordPressUserId(), 'CAST'));
        $this->assertSame($this->emails['ACTIVE'], $byPrimaryManager->email);

        $ended = $this->rows['WITHDRAWN']->id()->toString();
        $endedResult = $this->f->updateParticipant()->execute(new UpdateParticipantCommand($ended, $manager->wordPressUserId(), 'CAST'));
        $this->assertSame('WITHDRAWN', $endedResult->status);
        $this->assertSame('公演 WITHDRAWN', $endedResult->displayName);
        $this->assertNull($endedResult->subjectId);
        $this->assertNull($endedResult->personFamilyName);
        $this->assertNull($endedResult->email);
        $this->assertNull($endedResult->remarks);
    }
}
