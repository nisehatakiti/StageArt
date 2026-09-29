<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use StageArt\Domain\Person\Person;

/**
 * StageArt メンバー管理: Person ID検索 instruction (担当者権限をメンバー管理
 * へ統合・整理 §1/§2-A): deliberately minimal - only enough (id/family_name/
 * given_name) for a PrimaryManager/代理人 to confirm "is this the right
 * person?" before adding them as a Participant (POST
 * /productions/{id}/participants with subject_type=PERSON, an already-
 * existing capability - see CreateParticipantUseCase). Unlike
 * CurrentPersonResult (the caller's own Person), this never exposes
 * word_press_user_id or email_verified - those are internal/account-
 * level details irrelevant to identifying someone else, and no existing
 * StageArt endpoint exposes another Person's WordPress user id today.
 */
final class PersonSummaryResult
{
    public string $id;
    public ?string $familyName;
    public ?string $givenName;

    public function __construct(string $id, ?string $familyName, ?string $givenName)
    {
        $this->id = $id;
        $this->familyName = $familyName;
        $this->givenName = $givenName;
    }

    public static function fromDomain(Person $person): self
    {
        return new self($person->id()->toString(), $person->familyName(), $person->givenName());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'family_name' => $this->familyName,
            'given_name' => $this->givenName,
        ];
    }
}
