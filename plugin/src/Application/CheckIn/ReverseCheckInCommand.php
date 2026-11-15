<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class ReverseCheckInCommand
{
    public string $performanceId;
    public string $reservationId;
    public int $requestedByWordPressUserId;

    /**
     * The Ticket money was actually given back. Without it the Check-in
     * reversal leaves the recorded 入金・売上 untouched - see
     * CheckInProcessor::reverse().
     */
    public bool $paymentRefunded;

    public function __construct(string $performanceId, string $reservationId, int $requestedByWordPressUserId, bool $paymentRefunded = false)
    {
        $this->performanceId = $performanceId;
        $this->reservationId = $reservationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->paymentRefunded = $paymentRefunded;
    }
}
