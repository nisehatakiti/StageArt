<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\OrganizationInvitation;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\OrganizationInvitation\WordPressOrganizationInvitationMailer;
use StageArt\Infrastructure\WordPress\ParticipantInvitation\WordPressParticipantInvitationMailer;

/**
 * PR #7 R14-B stage 10 (D-1, approved B案の最小形): the organization
 * invitation mail links to the plain registration screen `{base}/register`
 * - no `token` parameter, and the invitation token appears nowhere in the
 * mail. The registration guidance in the body is unchanged. The
 * Production invitation mail (link to /participant-invitations) is not
 * affected.
 *
 * The WordPress functions the mailers call are stubbed in a separate
 * process; wp_mail() records what would be sent.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressOrganizationInvitationMailerTest extends TestCase
{
    private const TOKEN = 'org-invitation-token-0123456789abcdef';

    private static function stubWordPress(): void
    {
        $GLOBALS['r14b10_mail'] = [];
        eval('function esc_url($url) { return $url; }'
            . ' function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8"); }'
            . ' function esc_html__($text, $domain = "default") { return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8"); }'
            . ' function __($text, $domain = "default") { return $text; }'
            . ' function wp_mail($to, $subject, $message, $headers = []) { $GLOBALS["r14b10_mail"][] = compact("to", "subject", "message", "headers"); return true; }');
    }

    /** @return array{to: string, subject: string, message: string, headers: string[]} */
    private static function onlyMail(): array
    {
        self::assertCount(1, $GLOBALS['r14b10_mail']);

        return $GLOBALS['r14b10_mail'][0];
    }

    /** @return string[] every href in the mail body */
    private static function links(string $html): array
    {
        preg_match_all('/href="([^"]*)"/', $html, $matches);

        return $matches[1];
    }

    public function test_the_mail_links_to_the_registration_screen_without_a_token(): void
    {
        self::stubWordPress();
        $mailer = new WordPressOrganizationInvitationMailer('https://app.example.test/');

        $mailer->sendInvitationEmail('newcomer@example.com', '劇団テスト', self::TOKEN);

        $mail = self::onlyMail();
        $this->assertSame(['https://app.example.test/register'], self::links($mail['message']));
        $this->assertSame('https://app.example.test/register', $mailer->registrationUrl());
        $this->assertStringNotContainsString('token', $mail['message']);
        $this->assertStringNotContainsString('?', self::links($mail['message'])[0]);
        $this->assertStringNotContainsString(self::TOKEN, $mail['message']);
        $this->assertStringNotContainsString(self::TOKEN, $mail['subject']);
        $this->assertStringNotContainsString(self::TOKEN, implode("\n", $mail['headers']));
    }

    public function test_the_registration_guidance_subject_and_recipient_are_unchanged(): void
    {
        self::stubWordPress();
        $mailer = new WordPressOrganizationInvitationMailer('https://app.example.test');

        $mailer->sendInvitationEmail('newcomer@example.com', '劇団テスト', self::TOKEN);

        $mail = self::onlyMail();
        $this->assertSame('newcomer@example.com', $mail['to']);
        $this->assertSame('StageArt - 「劇団テスト」への招待', $mail['subject']);
        $this->assertStringContainsString('「劇団テスト」のメンバーとしてStageArtへ招待されました。', $mail['message']);
        $this->assertStringContainsString('下のボタンからStageArtへご登録いただくと、この団体のメンバーに追加されます。', $mail['message']);
        $this->assertStringContainsString('>StageArtに登録する</a>', $mail['message']);
        $this->assertStringContainsString('このリンクの有効期限は24時間です。', $mail['message']);
        $this->assertStringContainsString('心当たりがない場合は、このメールを破棄してください。', $mail['message']);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $mail['headers']);
    }

    public function test_the_organization_name_is_escaped(): void
    {
        self::stubWordPress();
        $mailer = new WordPressOrganizationInvitationMailer('https://app.example.test');

        $mailer->sendInvitationEmail('newcomer@example.com', '<b>劇団</b>', self::TOKEN);

        $this->assertStringContainsString('&lt;b&gt;劇団&lt;/b&gt;', self::onlyMail()['message']);
    }

    public function test_the_production_invitation_mail_still_links_to_the_invitation_list(): void
    {
        self::stubWordPress();
        $mailer = new WordPressParticipantInvitationMailer('https://app.example.test/');

        $mailer->sendInvitationEmail('invitee@example.com', '公演テスト');

        $mail = self::onlyMail();
        $this->assertSame(['https://app.example.test/participant-invitations'], self::links($mail['message']));
        $this->assertStringNotContainsString('/register', $mail['message']);
    }

    public function test_both_mailers_keep_the_same_base_url_wiring(): void
    {
        $plugin = (string) file_get_contents(__DIR__ . '/../../../../src/Presentation/Plugin.php');

        $this->assertStringContainsString('new WordPressOrganizationInvitationMailer($participantInvitationBaseUrl);', $plugin);
        $this->assertMatchesRegularExpression('/new WordPressParticipantInvitationMailer\(\$participantInvitationBaseUrl\)/', $plugin);
    }
}
