<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

/**
 * PR #7 R14-B stage 6 (design doc §7.3, D11 / U3): which personal fields of
 * one Participant row a viewer may see. Decided by
 * ParticipantDisclosurePolicy; applied by ParticipantResult::disclosed().
 *
 * - `identity`: `subject_id` (the Person id) and `remarks`. Withheld only
 *   on an ended row (CANCELLED / WITHDRAWN / REJECTED) - §9.1 "辞退した人物の
 *   ID・氏名は分からない".
 * - `names`: `person_family_name` / `person_given_name`.
 * - `email`.
 * - `consent`: `enrollment_source` / `consent_status` / `consented_at`.
 *
 * `id`, `production_id`, `subject_type`, `participant_type`, `status`,
 * `display_name` and the timestamps are always returned with a row.
 */
final class ParticipantDisclosure
{
    public bool $identity;
    public bool $names;
    public bool $email;
    public bool $consent;

    private function __construct(bool $identity, bool $names, bool $email, bool $consent)
    {
        $this->identity = $identity;
        $this->names = $names;
        $this->email = $email;
        $this->consent = $consent;
    }

    public static function full(): self
    {
        return new self(true, true, true, true);
    }

    /**
     * An ended row as a manager sees it: `display_name` and the status only.
     */
    public static function statusOnly(): self
    {
        return new self(false, false, false, false);
    }

    public static function of(bool $names, bool $email, bool $consent): self
    {
        return new self(true, $names, $email, $consent);
    }
}
