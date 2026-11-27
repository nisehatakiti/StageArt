<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Production\ProductionId;

/**
 * Creation defaults to ACTIVE rather than DRAFT: Participant.md defines
 * DRAFT as "参加がまだ確定していない状態" for a separate confirmation
 * step, but no such confirmation workflow exists in this phase (Phase 1
 * has no invitation/acceptance flow), so a Participant a manager adds
 * directly is effectively confirmed on creation. This is a disclosed
 * implementation judgment, not an explicit Blueprint default.
 */
final class Participant
{
    private ParticipantId $id;
    private ProductionId $productionId;
    private ParticipantSubjectType $subjectType;
    private string $subjectId;
    private ParticipantType $participantType;
    private ParticipantStatus $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    /**
     * StageArt Phase 1 (docs/21-MemberManagementScreen.md §2.6
     * "Remarks"): a Production-specific free-text note per member
     * ("チームA", "○○日は出演しないので..."), independent of any Person-
     * level profile field.
     */
    private ?string $remarks;
    /** Only meaningful when subjectType is NAME_ONLY - see
     * ParticipantSubjectType::NAME_ONLY's own docblock. */
    private ?string $displayName;
    /**
     * R14-B §4.5 (D2): the route by which this row was first created.
     * Set once at creation, never changed (row reuse keeps it). NULL =
     * created before R14-B.
     */
    private ?ParticipantEnrollmentSource $enrollmentSource;
    /**
     * R14-B §4.5: the Person's own most recent consent. Both NULL =
     * no recorded consent; NULL is never read as "consented" (existing
     * data stays unconsented).
     */
    private ?ParticipantConsentSource $consentSource;
    private ?DateTimeImmutable $consentedAt;
    /**
     * R14-B §6.1 (N4): when the latest join request (R14-B onwards) was
     * made - the basis of consentedAt on approval. NULL = a request made
     * before R14-B, or a row that is not a request; NULL is never read as
     * consent, and createdAt is never used in its place (a re-request
     * reuses the row, so createdAt stays the first request's). Set by
     * requestParticipation() (a new request) and reopenRequest() (a
     * re-request on the same row).
     */
    private ?DateTimeImmutable $requestedAt;

    private function __construct(
        ParticipantId $id,
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType,
        ParticipantStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $remarks = null,
        ?string $displayName = null,
        ?ParticipantEnrollmentSource $enrollmentSource = null,
        ?ParticipantConsentSource $consentSource = null,
        ?DateTimeImmutable $consentedAt = null,
        ?DateTimeImmutable $requestedAt = null
    ) {
        if (($consentSource === null) !== ($consentedAt === null)) {
            throw new InvalidArgumentException('consentSource and consentedAt must be both set or both null.');
        }

        $this->id = $id;
        $this->productionId = $productionId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->remarks = $remarks;
        $this->displayName = $displayName;
        $this->enrollmentSource = $enrollmentSource;
        $this->consentSource = $consentSource;
        $this->consentedAt = $consentedAt;
        $this->requestedAt = $requestedAt;
    }

    public static function create(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType,
        ?string $remarks = null,
        ?string $displayName = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ParticipantId::generate(),
            $productionId,
            $subjectType,
            $subjectId,
            $participantType,
            ParticipantStatus::active(),
            $now,
            $now,
            self::normalizeNullableString($remarks),
            self::normalizeNullableString($displayName)
        );
    }

    /**
     * A member registered by name only (docs/21-MemberManagementScreen.md
     * §2.4 "Member Registration Scope" - "People without a StageArt
     * account"). `subjectId` is a fresh, self-referential placeholder -
     * see ParticipantSubjectType::NAME_ONLY's docblock.
     */
    public static function createNameOnly(
        ProductionId $productionId,
        string $displayName,
        ParticipantType $participantType,
        ?string $remarks = null
    ): self {
        if (trim($displayName) === '') {
            throw new InvalidArgumentException('displayName must not be empty for a NAME_ONLY Participant.');
        }

        $now = new DateTimeImmutable();

        // R14-B §4.5: a NAME_ONLY member is a manager's record, not a
        // consent-bearing enrolment (consent stays NULL / not applicable).
        return new self(
            ParticipantId::generate(),
            $productionId,
            ParticipantSubjectType::nameOnly(),
            ParticipantId::generate()->toString(),
            $participantType,
            ParticipantStatus::active(),
            $now,
            $now,
            self::normalizeNullableString($remarks),
            self::normalizeNullableString($displayName),
            ParticipantEnrollmentSource::managerRecord()
        );
    }

    /**
     * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md,
     * 2026-11-27): a new ACTIVE PERSON Participant registered from an
     * administrator's invitation - at invitation time for a verified
     * account, or when the invited address is verified / signed in with
     * Google. The enrolment route is INVITATION (never changed later). No
     * consent is recorded: the person did not consent in StageArt (the
     * administrator confirmed their intention outside StageArt), so
     * consentSource / consentedAt stay NULL.
     */
    public static function enrollByInvitation(
        ProductionId $productionId,
        string $personId,
        ParticipantType $participantType,
        ?string $remarks = null,
        ?string $displayName = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ParticipantId::generate(),
            $productionId,
            ParticipantSubjectType::person(),
            $personId,
            $participantType,
            ParticipantStatus::active(),
            $now,
            $now,
            self::normalizeNullableString($remarks),
            self::normalizeNullableString($displayName),
            ParticipantEnrollmentSource::invitation()
        );
    }

    /**
     * R14-B §5.2 "(行なし) → ACTIVE" (history: the R14-B acceptance, removed
     * with the 2026-11-27 invitation policy; no current flow calls this - it
     * builds rows as accepted ones were stored): a new PERSON Participant created
     * because the Person themselves accepted an invitation. Records the
     * enrolment route (INVITATION, never changed later) and the Person's
     * consent (INVITATION at `$acceptedAt`) together. Row REUSE for an
     * existing (subject, type) row goes through reenroll() + recordConsent()
     * instead - a second row is never created for the same key (U13).
     */
    public static function enrollFromInvitation(
        ProductionId $productionId,
        string $personId,
        ParticipantType $participantType,
        DateTimeImmutable $acceptedAt,
        ?string $remarks = null,
        ?string $displayName = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ParticipantId::generate(),
            $productionId,
            ParticipantSubjectType::person(),
            $personId,
            $participantType,
            ParticipantStatus::active(),
            $now,
            $now,
            self::normalizeNullableString($remarks),
            self::normalizeNullableString($displayName),
            ParticipantEnrollmentSource::invitation(),
            ParticipantConsentSource::invitation(),
            $acceptedAt
        );
    }

    /**
     * The trailing R14-B arguments default to NULL so existing callers
     * (the current Repository, which has no columns for them yet) keep
     * reconstituting rows exactly as before: NULL = pre-R14-B / no
     * recorded consent.
     */
    public static function reconstitute(
        ParticipantId $id,
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType,
        ParticipantStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $remarks = null,
        ?string $displayName = null,
        ?ParticipantEnrollmentSource $enrollmentSource = null,
        ?ParticipantConsentSource $consentSource = null,
        ?DateTimeImmutable $consentedAt = null,
        ?DateTimeImmutable $requestedAt = null
    ): self {
        return new self(
            $id,
            $productionId,
            $subjectType,
            $subjectId,
            $participantType,
            $status,
            $createdAt,
            $updatedAt,
            $remarks,
            $displayName,
            $enrollmentSource,
            $consentSource,
            $consentedAt,
            $requestedAt
        );
    }

    /**
     * StageArt Web β版: a Person requesting to join a Production (via
     * Join Key or search - docs/04-DomainModel/JoinKey.md's "Production
     * Join Key... Participant Flow") starts PENDING, not ACTIVE - see
     * approve()/reject(). Mirrors Membership::requestMembership()'s same
     * request-then-approve shape at the Production Scope.
     */
    public static function requestParticipation(
        ProductionId $productionId,
        ParticipantSubjectType $subjectType,
        string $subjectId,
        ParticipantType $participantType,
        ?DateTimeImmutable $requestedAt = null
    ): self {
        $now = new DateTimeImmutable();

        // R14-B §5.2 "(行なし) → PENDING": the request time is recorded
        // (requestedAt), the basis of consentedAt when a manager approves it.
        return new self(
            ParticipantId::generate(),
            $productionId,
            $subjectType,
            $subjectId,
            $participantType,
            ParticipantStatus::fromString(ParticipantStatus::PENDING),
            $now,
            $now,
            null,
            null,
            ParticipantEnrollmentSource::joinRequest(),
            null,
            null,
            $requestedAt ?? $now
        );
    }

    /**
     * R14-B §5.2 / §7.4 (U13 A案): a new join request REUSES the existing
     * row for this (production, subject, type) instead of inserting a second
     * one: CANCELLED / REJECTED / WITHDRAWN / INACTIVE -> PENDING. The
     * Participant id, enrollmentSource, consentSource / consentedAt and
     * createdAt are kept; requestedAt becomes this request's time (consent
     * is recorded only when a manager approves it).
     *
     * Not allowed from ACTIVE or PENDING (already a member / already
     * requested) nor from DRAFT (N1: a DRAFT row is never turned into a
     * request) - the caller answers those with 409.
     */
    public function reopenRequest(DateTimeImmutable $requestedAt): void
    {
        $from = $this->status->toString();
        $allowed = [
            ParticipantStatus::CANCELLED,
            ParticipantStatus::REJECTED,
            ParticipantStatus::WITHDRAWN,
            ParticipantStatus::INACTIVE,
        ];

        if (! in_array($from, $allowed, true)) {
            throw new InvalidArgumentException("A {$from} Participant cannot be requested again.");
        }

        $this->requestedAt = $requestedAt;
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::PENDING));
    }

    public function isPending(): bool
    {
        return $this->status->toString() === ParticipantStatus::PENDING;
    }

    public function approve(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING Participant can be approved.');
        }

        $this->activate();
    }

    public function reject(): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException('Only a PENDING Participant can be rejected.');
        }

        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::REJECTED));
    }

    public function changeParticipantType(ParticipantType $participantType): void
    {
        $this->participantType = $participantType;
        $this->touch();
    }

    public function changeStatus(ParticipantStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function activate(): void
    {
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::ACTIVE));
    }

    public function deactivate(): void
    {
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::INACTIVE));
    }

    /**
     * Per Participant.md: "Participantは原則として物理削除しない" - the
     * Application layer's "delete" operation calls this instead of
     * removing the row.
     */
    public function cancel(): void
    {
        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::CANCELLED));
    }

    /**
     * R14-B §5.2 / §7.2 withdraw (D9): the Person leaves the Production
     * themselves. Only an ACTIVE PERSON Participant can withdraw; the row
     * is kept (WITHDRAWN), as are attendance/settlement records. Repeating
     * it on an already WITHDRAWN row is an idempotent no-op (returns
     * false). Who may call it (the Person themselves, not the
     * PrimaryManager) is checked in the Application layer.
     *
     * @return bool true when the status actually changed
     */
    public function withdraw(): bool
    {
        if (! $this->subjectType->equals(ParticipantSubjectType::person())) {
            throw new InvalidArgumentException('Only a PERSON Participant can withdraw.');
        }

        if ($this->status->toString() === ParticipantStatus::WITHDRAWN) {
            return false;
        }

        if (! $this->isActive()) {
            throw new InvalidArgumentException('Only an ACTIVE Participant can withdraw.');
        }

        $this->changeStatus(ParticipantStatus::fromString(ParticipantStatus::WITHDRAWN));

        return true;
    }

    /**
     * R14-B §5.2 row reuse (U13 A案) on invitation acceptance: bring the
     * existing row for this (production, subject, type) back to ACTIVE
     * instead of inserting a second row - the Participant id, the original
     * enrollmentSource and every attendance/settlement record linked to the
     * id stay as they are. Allowed from CANCELLED / WITHDRAWN / REJECTED /
     * INACTIVE / PENDING (a pending join request superseded by the
     * acceptance, §4.7). Not allowed from DRAFT (N11: acceptance is refused
     * while a DRAFT row exists) or ACTIVE (an already-ACTIVE Participant
     * only gets its consent recorded - recordConsent()). The caller records
     * consent with recordConsent() right after this.
     */
    public function reenroll(): void
    {
        if (! $this->subjectType->equals(ParticipantSubjectType::person())) {
            throw new InvalidArgumentException('Only a PERSON Participant can be re-enrolled.');
        }

        $from = $this->status->toString();
        $allowed = [
            ParticipantStatus::CANCELLED,
            ParticipantStatus::WITHDRAWN,
            ParticipantStatus::REJECTED,
            ParticipantStatus::INACTIVE,
            ParticipantStatus::PENDING,
        ];

        if (! in_array($from, $allowed, true)) {
            throw new InvalidArgumentException("A {$from} Participant cannot be re-enrolled.");
        }

        $this->changeStatus(ParticipantStatus::active());
    }

    /**
     * R14-B §4.5 / §5.2: record the Person's own consent (most recent one
     * wins - an earlier consent is kept only in the audit log, §6.3).
     * Only an ACTIVE PERSON Participant can carry consent: invitation
     * acceptance records it after (re)activating the row, and join-request
     * approval records it when it activates the row. NAME_ONLY and
     * ORGANIZATION subjects are outside consent ("NOT_APPLICABLE").
     * Never touches enrollmentSource.
     */
    public function recordConsent(ParticipantConsentSource $source, DateTimeImmutable $consentedAt): void
    {
        if (! $this->subjectType->equals(ParticipantSubjectType::person())) {
            throw new InvalidArgumentException('Consent can only be recorded for a PERSON Participant.');
        }

        if (! $this->isActive()) {
            throw new InvalidArgumentException('Consent can only be recorded for an ACTIVE Participant.');
        }

        $this->consentSource = $source;
        $this->consentedAt = $consentedAt;
        $this->touch();
    }

    /** R14-B §4.5: true only when consent was actually recorded. NULL
     * (existing data) is never treated as consented. */
    public function hasRecordedConsent(): bool
    {
        return $this->consentedAt !== null;
    }

    /**
     * Base condition for an effective ProductionDelegate: an ACTIVE PERSON
     * Participant. Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md,
     * 2026-11-27): the administrator confirms the person's intention before
     * inviting them, so recorded consent (consented_at) is no longer part of
     * the condition - it stays on the row as history only. This only
     * describes the Participant's own state - the delegate permission
     * decision itself (and the PrimaryManager's separate treatment) lives in
     * the Application layer, not here.
     */
    public function isActivePerson(): bool
    {
        return $this->subjectType->equals(ParticipantSubjectType::person())
            && $this->isActive();
    }

    public function isActive(): bool
    {
        return $this->status->toString() === ParticipantStatus::ACTIVE;
    }

    public function changeRemarks(?string $remarks): void
    {
        $this->remarks = self::normalizeNullableString($remarks);
        $this->touch();
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ParticipantId
    {
        return $this->id;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function subjectType(): ParticipantSubjectType
    {
        return $this->subjectType;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function participantType(): ParticipantType
    {
        return $this->participantType;
    }

    public function status(): ParticipantStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function enrollmentSource(): ?ParticipantEnrollmentSource
    {
        return $this->enrollmentSource;
    }

    public function consentSource(): ?ParticipantConsentSource
    {
        return $this->consentSource;
    }

    public function consentedAt(): ?DateTimeImmutable
    {
        return $this->consentedAt;
    }

    public function requestedAt(): ?DateTimeImmutable
    {
        return $this->requestedAt;
    }
}
