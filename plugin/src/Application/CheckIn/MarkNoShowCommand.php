<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class MarkNoShowCommand
{
    public string $performanceId;
    public string $reservationId;
    public int $requestedByWordPressUserId;

    /**
     * The 受付 received the Ticket money for this Reservation (e.g. handed
     * over by the member who collected it). Only then is 入金・売上
     * recorded - see CheckInProcessor::processNoShow().
     */
    public bool $paymentReceived;

    public function __construct(string $performanceId, string $reservationId, int $requestedByWordPressUserId, bool $paymentReceived = false)
    {
        $this->performanceId = $performanceId;
        $this->reservationId = $reservationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->paymentReceived = $paymentReceived;
    }
}
