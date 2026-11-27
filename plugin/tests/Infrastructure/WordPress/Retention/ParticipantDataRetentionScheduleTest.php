<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Retention;

use PHPUnit\Framework\TestCase;
use StageArt\Infrastructure\WordPress\Retention\ParticipantDataRetentionSchedule;

/**
 * PR #7 R14-B stage 8 (design doc §6.6): the retention job runs once a day
 * through WP-Cron; it is scheduled once (not again while scheduled) and
 * removed on deactivation. Plugin.php / stageart.php wire it in.
 *
 * The WP-Cron functions are stubbed in a separate process.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ParticipantDataRetentionScheduleTest extends TestCase
{
    private static function stubCron(): void
    {
        $GLOBALS['r14b8_cron'] = ['scheduled' => [], 'cleared' => [], 'next' => false];
        eval('function wp_next_scheduled($hook) { return $GLOBALS["r14b8_cron"]["next"]; }'
            . ' function wp_schedule_event($timestamp, $recurrence, $hook) { $GLOBALS["r14b8_cron"]["scheduled"][] = [$timestamp, $recurrence, $hook]; $GLOBALS["r14b8_cron"]["next"] = $timestamp; return true; }'
            . ' function wp_clear_scheduled_hook($hook) { $GLOBALS["r14b8_cron"]["cleared"][] = $hook; $GLOBALS["r14b8_cron"]["next"] = false; return 1; }');
    }

    public function test_it_is_scheduled_daily_once(): void
    {
        self::stubCron();

        ParticipantDataRetentionSchedule::ensureScheduled();
        ParticipantDataRetentionSchedule::ensureScheduled();

        $scheduled = $GLOBALS['r14b8_cron']['scheduled'];
        $this->assertCount(1, $scheduled, 'Not scheduled again while scheduled');
        $this->assertSame(['daily', 'stageart_participant_data_retention'], [$scheduled[0][1], $scheduled[0][2]]);
        $this->assertGreaterThan(time(), $scheduled[0][0]);
    }

    /** The follow-up purge of the rate-limit buckets: one single event within the hour, never two pending. */
    public function test_a_rate_limit_follow_up_is_scheduled_once_within_the_hour(): void
    {
        $GLOBALS['r14b8_cron'] = ['single' => [], 'next_single' => false];
        eval('function wp_next_scheduled($hook) { return $hook === "stageart_rate_limit_buckets_purge" ? $GLOBALS["r14b8_cron"]["next_single"] : false; }'
            . ' function wp_schedule_single_event($timestamp, $hook) { $GLOBALS["r14b8_cron"]["single"][] = [$timestamp, $hook]; $GLOBALS["r14b8_cron"]["next_single"] = $timestamp; return true; }');

        ParticipantDataRetentionSchedule::scheduleRateLimitFollowUp();
        ParticipantDataRetentionSchedule::scheduleRateLimitFollowUp();

        $single = $GLOBALS['r14b8_cron']['single'];
        $this->assertCount(1, $single, 'Not scheduled again while one is pending');
        $this->assertSame('stageart_rate_limit_buckets_purge', $single[0][1]);
        $this->assertGreaterThan(time(), $single[0][0]);
        $this->assertLessThanOrEqual(time() + 3600, $single[0][0], 'Within the hour');
    }

    public function test_the_follow_up_hook_runs_the_bucket_purge_only(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../../src/Infrastructure/WordPress/Retention/ParticipantDataRetentionSchedule.php');

        $this->assertMatchesRegularExpression('#add_action\(self::FOLLOW_UP_HOOK, static function \(\) use \(\$retention\): void \{\s*\$retention->runRateLimitBuckets\(\);\s*\}\);#', $source);
    }

    public function test_deactivation_removes_the_event(): void
    {
        self::stubCron();
        ParticipantDataRetentionSchedule::ensureScheduled();

        ParticipantDataRetentionSchedule::unschedule();

        $this->assertSame(['stageart_participant_data_retention', 'stageart_rate_limit_buckets_purge'], $GLOBALS['r14b8_cron']['cleared'], 'The daily job and a pending follow-up purge');
        $this->assertFalse(wp_next_scheduled('stageart_participant_data_retention'));
    }

    public function test_the_plugin_wires_the_job_the_rate_limits_and_the_deactivation_hook(): void
    {
        $plugin = (string) file_get_contents(__DIR__ . '/../../../../src/Presentation/Plugin.php');
        $main = (string) file_get_contents(__DIR__ . '/../../../../stageart.php');

        $this->assertStringContainsString('ParticipantDataRetentionSchedule::register(new WordPressParticipantDataRetention($wpdb));', $plugin);
        $this->assertMatchesRegularExpression('#new ResendParticipantInvitationUseCase\([^;]*\$invitationRateLimit\s*\);#s', $plugin);
        // Invitation policy 2026-11-27: the verified-account registration follows the rate limit argument.
        $this->assertMatchesRegularExpression('#new CreateParticipantInvitationUseCase\([^;]*\$invitationRateLimit,\s*\$findPersonByEmail,\s*\$invitationEnrollment\s*\);#s', $plugin);
        $this->assertMatchesRegularExpression('#new ParticipantInvitationRestController\((?:[^;]|;\s*\))*?\$invitationRateLimit\s*\);#s', $plugin);
        $this->assertStringContainsString("wp_salt('auth')", $plugin, 'The bucket key secret comes from WordPress, not the email_key keyring');
        $this->assertStringContainsString("register_deactivation_hook( STAGEART_PLUGIN_FILE, [ StageArt\\Infrastructure\\WordPress\\Retention\\ParticipantDataRetentionSchedule::class, 'unschedule' ] );", $main);
    }
}
