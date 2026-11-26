<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

/**
 * `PUT /participants/{id}`.
 *
 * R14-B §5.2 (stage 4, N6 / N8): `status` and `remarks` keep "not sent"
 * apart from "sent as null / empty": pass NOT_SENT (the default) when the
 * request did not contain the field. The values are kept as they arrived
 * (mixed) so that UpdateParticipantUseCase can refuse a number, a boolean
 * or an array with 422 instead of turning it into a string.
 * `participant_type` is required.
 */
final class UpdateParticipantCommand
{
    public const NOT_SENT = "\0stageart:not-sent";

    public string $participantId;
    public int $requestedByWordPressUserId;
    /** @var mixed */
    public $participantType;
    /** @var mixed NOT_SENT, or the value as sent */
    public $status;
    /** @var mixed NOT_SENT, or the value as sent */
    public $remarks;

    /**
     * @param mixed $participantType
     * @param mixed $status
     * @param mixed $remarks
     */
    public function __construct(
        string $participantId,
        int $requestedByWordPressUserId,
        $participantType,
        $status = self::NOT_SENT,
        $remarks = self::NOT_SENT
    ) {
        $this->participantId = $participantId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->participantType = $participantType;
        $this->status = $status;
        $this->remarks = $remarks;
    }

    public function sendsStatus(): bool
    {
        return $this->status !== self::NOT_SENT;
    }

    public function sendsRemarks(): bool
    {
        return $this->remarks !== self::NOT_SENT;
    }
}
