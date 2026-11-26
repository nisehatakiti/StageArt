<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\OrganizationInvitation;

use StageArt\Application\OrganizationInvitation\OrganizationInvitationMailerInterface;
use StageArt\Infrastructure\WordPress\Mail\StageArtMail;

/**
 * Mirrors WordPressParticipantInvitationMailer's own shape - see that
 * class's docblock for the established conventions this follows.
 *
 * PR #7 R14-B stage 10 (D-1, approved): the link is the plain registration
 * screen `{base}/register`, without the invitation token. The app never
 * used an organization invitation token from the link, and since R14-B an
 * old `/register?token=...` link is sent to login / the Production
 * invitation list. The invitee registers with the invited address and the
 * Membership is created when that address is verified (or on Google
 * sign-in with it) - ResolveOrganizationInvitationUseCase, unchanged. The
 * `$token` argument is still received (the interface and the invitation
 * itself are unchanged) but is not put in the mail.
 */
final class WordPressOrganizationInvitationMailer implements OrganizationInvitationMailerInterface
{
    private string $registrationBaseUrl;

    public function __construct(string $registrationBaseUrl)
    {
        $this->registrationBaseUrl = rtrim($registrationBaseUrl, '/');
    }

    /** The registration screen the invitation mail links to (no token). */
    public function registrationUrl(): string
    {
        return $this->registrationBaseUrl . '/register';
    }

    public function sendInvitationEmail(string $toEmail, string $organizationName, string $token): void
    {
        $registrationUrl = esc_url($this->registrationUrl());

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
            esc_html__('下のボタンからStageArtへご登録いただくと、この団体のメンバーに追加されます。', 'stageart'),
            $registrationUrl,
            esc_html__('StageArtに登録する', 'stageart'),
            esc_html__('このリンクの有効期限は24時間です。', 'stageart'),
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
