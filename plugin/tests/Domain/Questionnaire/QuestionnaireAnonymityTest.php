<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Questionnaire;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecord;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\ResponseAnswer;

/**
 * アンケート実装指示書 §50/§66's own "匿名性レビュー" as an enforced,
 * automated test rather than a one-off manual code search: fails the
 * build the moment anyone adds a respondent-identifying field to
 * QuestionnaireResponse (or the answer-carrying ResponseAnswer VO
 * embedded in it), instead of relying on a reviewer remembering to
 * re-check it by hand.
 */
final class QuestionnaireAnonymityTest extends TestCase
{
    private const BANNED_SUBSTRINGS = [
        'person',
        'reservation',
        'booker',
        'email',
        'account',
        'external_identity',
        'externalidentity',
        'user',
        'ticket',
        'checkin',
        'check_in',
        'attributed',
        'membership',
        'ip',
        'agent',
        'device',
        'cookie',
        'token',
    ];

    public function test_response_entity_declares_no_identifying_property(): void
    {
        $this->assertNoBannedNames(new ReflectionClass(QuestionnaireResponse::class));
    }

    public function test_response_answer_declares_no_identifying_property(): void
    {
        $this->assertNoBannedNames(new ReflectionClass(ResponseAnswer::class));
    }

    /**
     * §25's own "これはあくまでEmail送信重複防止用の業務情報である" - the invite
     * dedup record IS allowed to reference performanceId/reservationId (it
     * is email business data, not answer data), so it is deliberately
     * exempt from the banned-name sweep above; this test instead pins
     * down that it carries nothing beyond that pair plus a timestamp -
     * no answer content, no Questionnaire/Question/Response reference at
     * all.
     */
    public function test_invite_record_carries_no_response_or_questionnaire_reference(): void
    {
        $class = new ReflectionClass(QuestionnaireInviteRecord::class);
        $names = array_map(static fn ($property) => strtolower($property->getName()), $class->getProperties());

        sort($names);

        $this->assertSame(['performanceid', 'reservationid', 'sentat'], $names);
    }

    private function assertNoBannedNames(ReflectionClass $class): void
    {
        $names = [];

        foreach ($class->getProperties() as $property) {
            $names[] = $property->getName();
        }

        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $names[] = $parameter->getName();
        }

        foreach ($names as $name) {
            $normalized = strtolower($name);

            foreach (self::BANNED_SUBSTRINGS as $banned) {
                $this->assertStringNotContainsString(
                    $banned,
                    $normalized,
                    "{$class->getShortName()} must never carry a field resembling '{$banned}' (found: {$name})."
                );
            }
        }
    }
}
