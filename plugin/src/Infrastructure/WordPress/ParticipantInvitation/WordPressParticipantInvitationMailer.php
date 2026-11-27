<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\ParticipantInvitation;

use StageArt\Application\ParticipantInvitation\ParticipantInvitationMailerInterface;
use StageArt\Infrastructure\WordPress\Mail\StageArtMail;

/**
 * wp_mail()-only, no new dependency - mirrors WordPressAuthMailer /
 * WordPressQuestionnaireMailer's own established shape (HTML body via
 * sprintf(), single styled anchor tag). Reuses the
 * STAGEART_PARTICIPANT_INVITATION_BASE_URL Web host (see Plugin.php).
 *
 * PR #7 R14-B stage 4 (design doc §4.1 / §9.2 / §10, U5 C案): no token.
 * Invitation policy (2026-11-27): the link is `{base}/participant-invitations/{id}`
 * - the invitation's id, not a secret: the screen needs login, and the
 * server shows the invitation only to an account whose verified address is
 * the invited one. The invited person never accepts or rejects: they are
 * registered once the invited address is verified (or signed in with
 * Google). Returns StageArtMail::send()'s result (the mail server accepted
 * it; not delivery).
 */
final class WordPressParticipantInvitationMailer implements ParticipantInvitationMailerInterface
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function invitationUrl(string $invitationId): string
    {
        return $this->baseUrl . '/participant-invitations/' . rawurlencode($invitationId);
    }

    public function sendInvitationEmail(string $toEmail, string $productionName, string $invitationId): bool
    {
        $body = sprintf(
            '<div style="font-family: sans-serif; font-size: 15px; line-height: 1.7; color: #2A2320;">'
                . '<p>%1$s</p>'
                . '<p>%2$s</p>'
                . '<p style="text-align:center; margin: 28px 0;">'
                    . '<a href="%3$s" style="display:inline-block; background-color:#C4432F; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">%4$s</a>'
                . '</p>'
                . '<p>%5$s</p>'
                . '<p>%6$s</p>'
                . '<p>%7$s</p>'
            . '</div>',
            sprintf(
                /* translators: %s: Production name */
                esc_html__('「%s」のメンバーとしてStageArtへ招待されました。', 'stageart'),
                esc_html($productionName)
            ),
            esc_html__('このメールを受け取ったメールアドレスでStageArtに登録し、メールアドレスの確認を済ませると、この公演の参加者として登録されます。', 'stageart'),
            esc_url($this->invitationUrl($invitationId)),
            esc_html__('招待を確認する', 'stageart'),
            esc_html__('招待の有効期限は7日間です。', 'stageart'),
            esc_html__('登録の後にこのメールのボタンをもう一度押すと、ログインした状態で招待の内容を確認できます。', 'stageart'),
            esc_html__('心当たりがない場合は、このメールを破棄してください。', 'stageart')
        );

        return StageArtMail::send(
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
