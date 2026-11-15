<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

/**
 * §22/§23/§32/§62-禁止3/4/5: mirrors AuthMailerInterface's own shape (a
 * raw destination email string, no PersonId) rather than going through
 * NotificationContract - see SendQuestionnaireInvitesForPerformanceUseCase's
 * own docblock for why. `publicUrl` is always the one common
 * public Questionnaire URL - never anything per-recipient.
 */
interface QuestionnaireMailerInterface
{
    public function sendInviteEmail(string $toEmail, string $productionName, string $publicUrl): void;
}
