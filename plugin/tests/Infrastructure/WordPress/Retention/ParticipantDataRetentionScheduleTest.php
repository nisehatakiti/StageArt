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

    public function test_deactivation_removes_the_event(): void
    {
        self::stubCron();
        ParticipantDataRetentionSchedule::ensureScheduled();

        ParticipantDataRetentionSchedule::unschedule();

        $this->assertSame(['stageart_participant_data_retention'], $GLOBALS['r14b8_cron']['cleared']);
        $this->assertFalse(wp_next_scheduled('stageart_participant_data_retention'));
    }

    public function test_the_plugin_wires_the_job_the_rate_limits_and_the_deactivation_hook(): void
    {
        $plugin = (string) file_get_contents(__DIR__ . '/../../../../src/Presentation/Plugin.php');
        $main = (string) file_get_contents(__DIR__ . '/../../../../stageart.php');

        $this->assertStringContainsString('ParticipantDataRetentionSchedule::register(new WordPressParticipantDataRetention($wpdb));', $plugin);
        $this->assertMatchesRegularExpression('#new ResendParticipantInvitationUseCase\([^;]*\$invitationRateLimit\s*\);#s', $plugin);
        $this->assertMatchesRegularExpression('#new CreateParticipantInvitationUseCase\([^;]*\$invitationRateLimit\s*\);#s', $plugin);
        $this->assertMatchesRegularExpression('#new ParticipantInvitationRestController\((?:[^;]|;\s*\))*?\$invitationRateLimit\s*\);#s', $plugin);
        $this->assertStringContainsString("wp_salt('auth')", $plugin, 'The bucket key secret comes from WordPress, not the email_key keyring');
        $this->assertStringContainsString("register_deactivation_hook( STAGEART_PLUGIN_FILE, [ StageArt\\Infrastructure\\WordPress\\Retention\\ParticipantDataRetentionSchedule::class, 'unschedule' ] );", $main);
    }
}
