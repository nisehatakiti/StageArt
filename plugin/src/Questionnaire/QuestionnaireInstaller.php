<?php

declare(strict_types=1);

namespace StageArt\Questionnaire;

/**
 * StageArt Core/Module Architecture: the Questionnaire Module's own 4
 * tables, following PerformanceInstaller/CheckInInstaller's exact
 * precedent - Core's own Installer::install() calls this rather than
 * creating these tables itself.
 *
 * §60: `stageart_questionnaires.production_id` is UNIQUE, which is what
 * actually enforces "V1では1 Production : 1 Questionnaire" at the storage
 * layer (CreateQuestionnaireUseCase's own findByProductionId() check is
 * the Application-layer half of that same rule).
 *
 * §10/§50: `stageart_questionnaire_responses` intentionally has no
 * person_id/reservation_id/email/ip/user_agent column - see
 * WordPressQuestionnaireResponseRepository's own docblock.
 * `stageart_questionnaire_invite_sent` is a completely separate table
 * from Responses (§25's own "Reservation → Responseの関連を作るためのデータ
 * として利用してはいけない") - its composite key is
 * (performance_id, reservation_id), with no `id` column at all, since it
 * is a pure "did we already send this" fact, not an Aggregate with its
 * own identity.
 */
final class QuestionnaireInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $questionnaires = $wpdb->prefix . 'stageart_questionnaires';
        $questions = $wpdb->prefix . 'stageart_questionnaire_questions';
        $responses = $wpdb->prefix . 'stageart_questionnaire_responses';
        $inviteSent = $wpdb->prefix . 'stageart_questionnaire_invite_sent';

        dbDelta("CREATE TABLE {$questionnaires} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            response_end_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            created_by CHAR(36) NULL,
            updated_by CHAR(36) NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY production_id (production_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$questions} (
            id CHAR(36) NOT NULL,
            questionnaire_id CHAR(36) NOT NULL,
            text TEXT NOT NULL,
            type VARCHAR(30) NOT NULL,
            required TINYINT(1) NOT NULL DEFAULT 0,
            display_order INT NOT NULL DEFAULT 0,
            choices_json TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY questionnaire_id (questionnaire_id)
        ) {$charsetCollate};");

        // §9/§10/§50: no person_id/reservation_id/email/account_id/
        // ip/user_agent/device_id/token column - see
        // QuestionnaireResponse::class and WordPressQuestionnaireResponseRepository's
        // own docblocks. Do not add one here without re-reading both.
        dbDelta("CREATE TABLE {$responses} (
            id CHAR(36) NOT NULL,
            questionnaire_id CHAR(36) NOT NULL,
            answers_json TEXT NOT NULL,
            submitted_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY questionnaire_id (questionnaire_id)
        ) {$charsetCollate};");

        // §25/§50: Email-dedup bookkeeping only - never joined against
        // stageart_questionnaire_responses.
        dbDelta("CREATE TABLE {$inviteSent} (
            performance_id CHAR(36) NOT NULL,
            reservation_id CHAR(36) NOT NULL,
            sent_at DATETIME NOT NULL,
            PRIMARY KEY  (performance_id, reservation_id)
        ) {$charsetCollate};");
    }
}
