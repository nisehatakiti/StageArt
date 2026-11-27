<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\OrganizationInvitation;

use StageArt\Application\OrganizationInvitation\OrganizationInvitationMailerInterface;
use StageArt\Infrastructure\WordPress\Mail\StageArtMail;

/**
 * Mirrors WordPressParticipantInvitationMailer's own shape - see that
 * class's docblock for the established conventions this follows.
 *
 * PR #7 R14-B stage 10 (D-1, approved): no invitation token in the mail.
 * Invitation policy (2026-11-27): the link is `{base}/organization-invitations/{id}`
 * - the invitation's id, not a secret: the screen needs login, and the
 * server shows the invitation only to an account whose verified address is
 * the invited one. The invitee registers with the invited address and the
 * Membership is created when that address is verified (or on Google
 * sign-in with it) - ResolveOrganizationInvitationUseCase, unchanged. The
 * `$token` argument is still received (the invitation itself is unchanged)
 * but is not put in the mail.
 */
final class WordPressOrganizationInvitationMailer implements OrganizationInvitationMailerInterface
{
    private string $registrationBaseUrl;

    public function __construct(string $registrationBaseUrl)
    {
        $this->registrationBaseUrl = rtrim($registrationBaseUrl, '/');
    }

    /** The invitation screen the invitation mail links to (the id, no token). */
    public function invitationUrl(string $invitationId): string
    {
        return $this->registrationBaseUrl . '/organization-invitations/' . rawurlencode($invitationId);
    }

    public function sendInvitationEmail(string $toEmail, string $organizationName, string $token, string $invitationId): void
    {
        $invitationUrl = esc_url($this->invitationUrl($invitationId));

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
                /* translators: %s: Organization name */
                esc_html__('「%s」のメンバーとしてStageArtへ招待されました。', 'stageart'),
                esc_html($organizationName)
            ),
            esc_html__('このメールを受け取ったメールアドレスでStageArtに登録し、メールアドレスの確認を済ませると、この団体のメンバーとして登録されます。', 'stageart'),
            $invitationUrl,
            esc_html__('招待を確認する', 'stageart'),
            esc_html__('このリンクの有効期限は24時間です。登録の後にこのメールのボタンをもう一度押すと、ログインした状態で招待の内容を確認できます。', 'stageart'),
            esc_html__('心当たりがない場合は、このメールを破棄してください。', 'stageart')
        );

        StageArtMail::send(
            $toEmail,
            sprintf(
                /* translators: %s: Organization name */
                __('StageArt - 「%s」への招待', 'stageart'),
                $organizationName
            ),
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
