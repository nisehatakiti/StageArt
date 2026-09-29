<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\ParticipantInvitation;

use StageArt\Application\ParticipantInvitation\ParticipantInvitationMailerInterface;

/**
 * wp_mail()-only, no new dependency - mirrors WordPressAuthMailer /
 * WordPressQuestionnaireMailer's own established shape exactly (HTML
 * body via sprintf(), single styled anchor tag, token never printed as
 * visible plain text - only inside the link's own query string). Reuses
 * the same STAGEART_EMAIL_VERIFICATION_BASE_URL Web host
 * WordPressAuthMailer already uses (see Plugin.php's wiring) rather than
 * introducing a second base-URL config, since both mailers point at the
 * same mobile-rn Web export.
 */
final class WordPressParticipantInvitationMailer implements ParticipantInvitationMailerInterface
{
    private string $registrationBaseUrl;

    public function __construct(string $registrationBaseUrl)
    {
        $this->registrationBaseUrl = rtrim($registrationBaseUrl, '/');
    }

    public function sendInvitationEmail(string $toEmail, string $productionName, string $token): void
    {
        $registrationUrl = esc_url($this->registrationBaseUrl . '/register?token=' . rawurlencode($token));

        $body = sprintf(
            '<div style="font-family: sans-serif; font-size: 15px; line-height: 1.7; color: #2A2320;">'
                . '<p>%1$s</p>'
                . '<p>%2$s</p>'
                . '<p style="text-align:center; margin: 28px 0;">'
                    . '<a href="%3$s" style="display:inline-block; background-color:#C4432F; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">%4$s</a>'
                . '</p>'
                . '<p>%5$s</p>'
                . '<p>%6$s</p>'
            . '</div>',
            sprintf(
                /* translators: %s: Production name */
                esc_html__('「%s」のメンバーとしてStageArtへ招待されました。', 'stageart'),
                esc_html($productionName)
            ),
            esc_html__('下のボタンからStageArtへご登録いただくと、この公演のメンバーに追加されます。', 'stageart'),
            $registrationUrl,
            esc_html__('StageArtに登録する', 'stageart'),
            esc_html__('このリンクの有効期限は24時間です。', 'stageart'),
            esc_html__('心当たりがない場合は、このメールを破棄してください。', 'stageart')
        );

        wp_mail(
            $toEmail,
            sprintf(
                /* translators: %s: Production name */
                __('StageArt - 「%s」への招待', 'stageart'),
                $productionName
            ),
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
