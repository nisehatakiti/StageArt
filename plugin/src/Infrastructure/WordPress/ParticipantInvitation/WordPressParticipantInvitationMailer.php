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
 * The link is always `{base}/participant-invitations` - the invitee's own
 * invitation list, login required - so opening it shows nothing about the
 * invitation by itself. The text is the same whether or not the address
 * belongs to an account. Returns StageArtMail::send()'s result (the mail
 * server accepted it; not delivery).
 */
final class WordPressParticipantInvitationMailer implements ParticipantInvitationMailerInterface
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function invitationListUrl(): string
    {
        return $this->baseUrl . '/participant-invitations';
    }

    public function sendInvitationEmail(string $toEmail, string $productionName): bool
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
            esc_html__('このメールを受け取ったメールアドレスでStageArtにログインすると（未登録の場合は、登録とメールアドレスの確認の後に）、招待を確認できます。承諾するまで参加者にはなりません。', 'stageart'),
            esc_url($this->invitationListUrl()),
            esc_html__('招待を確認する', 'stageart'),
            esc_html__('招待の有効期限は7日間です。', 'stageart'),
            esc_html__('招待が見つからない場合は、このメールを受け取ったメールアドレスでログインしてください。', 'stageart'),
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
