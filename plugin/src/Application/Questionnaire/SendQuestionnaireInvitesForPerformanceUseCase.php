<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use DateTimeImmutable;
use Throwable;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecord;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecordRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Reservation\ReservationStatus;

/**
 * §20/§21/§25/§43/§44/§45/§46/§47/§48: the invite-Email fan-out for one
 * finished Performance. Reservation is used for exactly one purpose here -
 * "who to email" (§11/§23) - and nothing this class reads from Reservation
 * (bookerEmail, guestCount, status) is ever written to a
 * QuestionnaireResponse or the invite-sent dedup table beyond
 * performanceId/reservationId themselves (see QuestionnaireInviteRecord's
 * own docblock).
 *
 * §42/§51/AC-51: deliberately swallows every exception around a single
 * recipient's send (and the whole method never throws) - a WordPress
 * `wp_mail()` failure, or any other error for one Reservation, must never
 * abort the rest of the batch or bubble up into the caller
 * (UpdatePerformanceUseCase), which would turn an Email problem into a
 * failed Performance update.
 */
final class SendQuestionnaireInvitesForPerformanceUseCase
{
    private PerformanceRepositoryInterface $performances;
    private QuestionnaireRepositoryInterface $questionnaires;
    private ReservationRepositoryInterface $reservations;
    private QuestionnaireInviteRecordRepositoryInterface $inviteRecords;
    private QuestionnaireMailerInterface $mailer;
    private QuestionnairePublicUrlResolver $publicUrlResolver;
    private ProductionContextContract $productionContext;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        QuestionnaireRepositoryInterface $questionnaires,
        ReservationRepositoryInterface $reservations,
        QuestionnaireInviteRecordRepositoryInterface $inviteRecords,
        QuestionnaireMailerInterface $mailer,
        QuestionnairePublicUrlResolver $publicUrlResolver,
        ProductionContextContract $productionContext
    ) {
        $this->performances = $performances;
        $this->questionnaires = $questionnaires;
        $this->reservations = $reservations;
        $this->inviteRecords = $inviteRecords;
        $this->mailer = $mailer;
        $this->publicUrlResolver = $publicUrlResolver;
        $this->productionContext = $productionContext;
    }

    public function execute(PerformanceId $performanceId): void
    {
        try {
            $this->send($performanceId);
        } catch (Throwable $exception) {
            // §42/§51: never let an invite-Email problem surface as a
            // Performance-update failure - this Use Case's entire
            // contract to its caller is "best effort, never throws".
        }
    }

    private function send(PerformanceId $performanceId): void
    {
        $performance = $this->performances->findById($performanceId);

        if ($performance === null) {
            return;
        }

        // §44: no Questionnaire at all -> nothing to send, and this is
        // not an error condition.
        $questionnaire = $this->questionnaires->findByProductionId($performance->productionId());

        if ($questionnaire === null) {
            return;
        }

        // §45/§47/§48: DRAFT, CLOSED, or past its own responseEndAt -> no
        // invite Email (§16's Backend-side enforcement mirrored here).
        if (! $questionnaire->isAcceptingResponses(new DateTimeImmutable())) {
            return;
        }

        $publicUrl = $this->publicUrlResolver->resolve($performance->productionId());

        if ($publicUrl === null) {
            return;
        }

        $production = $this->productionContext->getProduction($performance->productionId());

        if ($production === null) {
            return;
        }

        $productionName = $production->name;

        // §21/§43: CHECKED_IN only, one Email per Reservation regardless
        // of guestCount (§30/AC-30 - never per-guest).
        foreach ($this->reservations->findByPerformanceId($performanceId) as $reservation) {
            if (! $reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))) {
                continue;
            }

            if ($this->inviteRecords->exists($performanceId, $reservation->id())) {
                continue;
            }

            try {
                $this->mailer->sendInviteEmail($reservation->bookerEmail(), $productionName, $publicUrl);
            } catch (Throwable $exception) {
                continue;
            }

            $this->inviteRecords->save(QuestionnaireInviteRecord::create($performanceId, $reservation->id()));
        }
    }
}
