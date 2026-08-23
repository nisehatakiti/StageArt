<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Authentication;

use StageArt\Application\Authentication\AuthMailerInterface;

/**
 * Uses wp_mail() only - no new dependency (WordPress core already
 * provides it). sendPasswordResetEmail() still sends the raw opaque
 * token as plain text (that screen's own design, unchanged, still
 * expects a manually-entered code - see reset-password.tsx);
 * sendEmailVerificationEmail() sends an HTML message with a single
 * tappable link instead (a real-device correction - see its own
 * docblock). The hidden WordPress User StageArt provisions for its own
 * users is not involved in sending either email - wp_mail() sends from
 * the site's own configured From-address, not from any per-user
 * WordPress account.
 */
final class WordPressAuthMailer implements AuthMailerInterface
{
    private string $emailVerificationBaseUrl;

    /**
     * StageArt Authentication Phase 6: the Web confirmation host is
     * injected, not hardcoded, so the dev environment
     * (dev-stageart.hatakiti.com) and a future production host
     * (e.g. stageart.top) can differ without touching this class - see
     * Plugin.php's wiring for the STAGEART_EMAIL_VERIFICATION_BASE_URL
     * wp-config.php constant this comes from.
     */
    public function __construct(string $emailVerificationBaseUrl)
    {
        $this->emailVerificationBaseUrl = rtrim($emailVerificationBaseUrl, '/');
    }

    public function sendPasswordResetEmail(string $toEmail, string $token): void
    {
        wp_mail(
            $toEmail,
            __('StageArt - パスワードリセット', 'stageart'),
            sprintf(
                /* translators: %s: password reset token */
                __("以下のトークンを使用してパスワードをリセットしてください。\n\nトークン: %s\n\nこのトークンの有効期限は1時間です。心当たりがない場合は、このメールを無視してください。", 'stageart'),
                $token
            )
        );
    }

    /**
     * StageArt Authentication Phase 6: the link is now an HTTPS Web
     * confirmation page (mobile-rn's verify-email.tsx, exported as a
     * static Web route via `expo export --platform web` - confirmed
     * working with zero code changes during this Phase's investigation)
     * rather than the stageart:// Custom URL Scheme - a scheme link
     * cannot be opened on a device without the app installed (PC
     * browser, a different phone/tablet), which defeated the whole
     * point of confirming an email address from wherever the user
     * happens to be reading it. The Custom Scheme itself is NOT removed
     * (app.config.ts's `scheme: 'stageart'` stays, and the Web page
     * offers its own "StageArtアプリを開く" button using it) - only this
     * email's own primary link changed.
     *
     * The token still never appears as visible plain text in the email
     * body, including its own query string - a plain-text sprintf()
     * body would show the raw URL as literal text, so this remains an
     * HTML message (Content-Type header + a small hand-written HTML
     * body, no new mailer dependency) with a single styled anchor tag
     * reading "メールアドレスを確認する" - the token lives solely in that
     * anchor's href.
     */
    public function sendEmailVerificationEmail(string $toEmail, string $token): void
    {
        $verificationUrl = esc_url($this->emailVerificationBaseUrl . '/verify-email?token=' . rawurlencode($token));

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
            esc_html__('StageArtにご登録いただきありがとうございます。', 'stageart'),
            esc_html__('メールアドレスの確認を完了するには、下のボタンを押してください。', 'stageart'),
            $verificationUrl,
            esc_html__('メールアドレスを確認する', 'stageart'),
            esc_html__('このリンクの有効期限は24時間です。', 'stageart'),
            esc_html__('心当たりがない場合は、このメールを破棄してください。', 'stageart')
        );

        wp_mail(
            $toEmail,
            __('StageArt - メールアドレスの確認', 'stageart'),
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
