<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Questionnaire;

use StageArt\Application\Questionnaire\QuestionnaireMailerInterface;

/**
 * §22/§23: mirrors WordPressAuthMailer's own "wp_mail() only, no new
 * dependency" shape - see QuestionnaireMailerInterface's own docblock for
 * why this does not go through NotificationContract/wp_mail is never
 * checked for a return value here either, matching every other Mailer in
 * this codebase (a delivery failure is never allowed to throw - see
 * SendQuestionnaireInvitesForPerformanceUseCase's own docblock for why
 * that already does not matter for §42/§51's rollback guarantee).
 */
final class WordPressQuestionnaireMailer implements QuestionnaireMailerInterface
{
    public function sendInviteEmail(string $toEmail, string $productionName, string $publicUrl): void
    {
        $safeUrl = esc_url($publicUrl);

        $body = sprintf(
            '<div style="font-family: sans-serif; font-size: 15px; line-height: 1.7; color: #2A2320;">'
                . '<p>%1$s</p>'
                . '<p>%2$s</p>'
                . '<p style="text-align:center; margin: 28px 0;">'
                    . '<a href="%3$s" style="display:inline-block; background-color:#C4432F; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">%4$s</a>'
                . '</p>'
                . '<p>%5$s</p>'
            . '</div>',
            sprintf(
                /* translators: %s: Production name */
                esc_html__('この度は「%s」にご来場いただき、誠にありがとうございました。', 'stageart'),
                esc_html($productionName)
            ),
            esc_html__('今後の公演をより良いものにするため、アンケートにご協力いただけますと幸いです。', 'stageart'),
            $safeUrl,
            esc_html__('アンケートに回答する', 'stageart'),
            esc_html__('このアンケートは匿名で、どなたでもご回答いただけます。', 'stageart')
        );

        wp_mail(
            $toEmail,
            sprintf(
                /* translators: %s: Production name */
                __('StageArt - 「%s」アンケートのお願い', 'stageart'),
                $productionName
            ),
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
