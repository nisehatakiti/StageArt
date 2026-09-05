<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

final class AddRehearsalAttendanceTargetsCommand
{
    public string $rehearsalId;
    public int $requestedByWordPressUserId;
    /** @var string[] */
    public array $personIds;

    /**
     * @param string[] $personIds
     */
    public function __construct(string $rehearsalId, int $requestedByWordPressUserId, array $personIds)
    {
        $this->rehearsalId = $rehearsalId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->personIds = $personIds;
    }
}
