<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\Person;

/**
 * StageArt メンバー管理 instruction (担当者権限をメンバー管理へ統合・整理 §1):
 * `personFamilyName`/`personGivenName` are resolved the same way
 * ProductionDelegateResult already resolves its own target Person's name
 * (both nullable, matching Person::familyName()/givenName()'s own
 * nullability - a Person who has not completed set-name.tsx yet). Only
 * meaningful when subjectType is PERSON; null for NAME_ONLY/ORGANIZATION
 * subjects (NAME_ONLY already has its own displayName field instead).
 *
 * PR #7 R14-B stage 6 (§4.5, §7.1, §7.3): each row also carries
 * `enrollment_source`, `consent_status` (CONFIRMED / UNCONFIRMED /
 * NOT_APPLICABLE) and `consented_at`; and what a viewer sees of a row is
 * cut down by ParticipantDisclosurePolicy - a withheld field is returned
 * as null (disclosed()).
 */
final class ParticipantResult
{
    public const CONSENT_CONFIRMED = 'CONFIRMED';
    public const CONSENT_UNCONFIRMED = 'UNCONFIRMED';
    public const CONSENT_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public string $id;
    public string $productionId;
    public string $subjectType;
    public ?string $subjectId;
    public string $participantType;
    public string $status;
    public string $createdAt;
    public string $updatedAt;
    public ?string $remarks;
    public ?string $displayName;
    public ?string $personFamilyName;
    public ?string $personGivenName;
    public ?string $email;
    public ?string $enrollmentSource;
    public ?string $consentStatus;
    public ?string $consentedAt;

    private function __construct(Participant $participant, ParticipantDisclosure $disclosure, ?Person $person, ?string $email)
    {
        $this->id = $participant->id()->toString();
        $this->productionId = $participant->productionId()->toString();
        $this->subjectType = $participant->subjectType()->toString();
        $this->subjectId = $disclosure->identity ? $participant->subjectId() : null;
        $this->participantType = $participant->participantType()->toString();
        $this->status = $participant->status()->toString();
        $this->createdAt = $participant->createdAt()->format(DATE_ATOM);
        $this->updatedAt = $participant->updatedAt()->format(DATE_ATOM);
        $this->remarks = $disclosure->identity ? $participant->remarks() : null;
        $this->displayName = $participant->displayName();
        $this->personFamilyName = $disclosure->names ? $person?->familyName() : null;
        $this->personGivenName = $disclosure->names ? $person?->givenName() : null;
        $this->email = $disclosure->email ? $email : null;
        $this->enrollmentSource = $disclosure->consent ? $participant->enrollmentSource()?->toString() : null;
        $this->consentStatus = $disclosure->consent ? self::consentStatusOf($participant) : null;
        $this->consentedAt = $disclosure->consent ? $participant->consentedAt()?->format(DATE_ATOM) : null;
    }

    /**
     * StageArt メンバー一覧メールアドレス表示ラウンド: `$email` is resolved by
     * the caller via the existing PersonEmailResolver (the same "single
     * resolution point for both delivery AND display" Settings'
     * notification-email screen already uses - see that class's own
     * docblock), never a new field stored on Participant itself. Null
     * for a NAME_ONLY/ORGANIZATION subject (no Person to resolve), and
     * also null for a PERSON subject with no deliverable address found
     * in any of PersonEmailResolver's sources - never a reason to fail
     * this call.
     *
     * Every field, as a full disclosure (a NAME_ONLY row just created by
     * a manager; tests).
     */
    public static function fromDomain(Participant $participant, ?Person $person = null, ?string $email = null): self
    {
        return new self($participant, ParticipantDisclosure::full(), $person, $email);
    }

    /**
     * R14-B stage 6: only the fields `$disclosure` allows; the others are null.
     */
    public static function disclosed(Participant $participant, ParticipantDisclosure $disclosure, ?Person $person = null, ?string $email = null): self
    {
        return new self($participant, $disclosure, $person, $email);
    }

    /**
     * §4.5: CONFIRMED when consent is recorded; a PERSON without it is
     * UNCONFIRMED (rows made before R14-B included); NAME_ONLY and
     * ORGANIZATION rows are NOT_APPLICABLE.
     */
    private static function consentStatusOf(Participant $participant): string
    {
        if (! $participant->subjectType()->equals(ParticipantSubjectType::person())) {
            return self::CONSENT_NOT_APPLICABLE;
        }

        return $participant->consentedAt() !== null ? self::CONSENT_CONFIRMED : self::CONSENT_UNCONFIRMED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'production_id' => $this->productionId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'participant_type' => $this->participantType,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'remarks' => $this->remarks,
            'display_name' => $this->displayName,
            'person_family_name' => $this->personFamilyName,
            'person_given_name' => $this->personGivenName,
            'email' => $this->email,
            'enrollment_source' => $this->enrollmentSource,
            'consent_status' => $this->consentStatus,
            'consented_at' => $this->consentedAt,
        ];
    }
}
