<?php

declare(strict_types=1);

namespace StageArt\Tests\Core;

use PHPUnit\Framework\TestCase;

/**
 * StageArt Core/Module Architecture
 * (docs/architecture/CoreModuleArchitecture.md): a structural guard, not
 * a behavior test - scans the Rehearsal Module's actual source files
 * (Application/Rehearsal, Application/RehearsalAttendance,
 * Application/Timetable, Application/TimetableItem,
 * Application/ScheduleComment, and their Domain counterparts) and fails
 * if any of them imports a concrete `Infrastructure\WordPress\*` class
 * directly. The Rehearsal Module is expected to depend on Domain
 * Repository *interfaces* and Core Contracts, never on a concrete
 * WordPress persistence implementation - that indirection is what makes
 * a future WordPress Adapter swap (§12) possible at all.
 */
final class RehearsalModuleDependencyDirectionTest extends TestCase
{
    private const REHEARSAL_MODULE_DIRECTORIES = [
        'Application/Rehearsal',
        'Application/RehearsalAttendance',
        'Application/Timetable',
        'Application/TimetableItem',
        'Application/ScheduleComment',
        'Domain/Rehearsal',
        'Domain/RehearsalAttendance',
        'Domain/Timetable',
        'Domain/TimetableItem',
        'Domain/ScheduleComment',
    ];

    public function test_rehearsal_module_never_imports_concrete_infrastructure_classes(): void
    {
        $srcRoot = dirname(__DIR__, 2) . '/src';
        $violations = [];

        foreach (self::REHEARSAL_MODULE_DIRECTORIES as $relativeDir) {
            $dir = $srcRoot . '/' . $relativeDir;

            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.php') as $file) {
                $contents = file_get_contents($file);

                if (preg_match_all('/^use\s+(StageArt\\\\Infrastructure\\\\[^\s;]+);/m', $contents, $matches)) {
                    foreach ($matches[1] as $import) {
                        $violations[] = basename($file) . ' imports ' . $import;
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Rehearsal Module files must not import concrete Infrastructure classes:\n" . implode("\n", $violations));
    }

    /**
     * The companion positive check: the Rehearsal Module's Application
     * layer does reference Core's Contract interfaces
     * (`StageArt\Core\Contract\*`) for at least its Membership need -
     * confirming the Contract boundary is actually adopted somewhere,
     * not merely defined and unused.
     */
    public function test_rehearsal_module_uses_at_least_one_core_contract(): void
    {
        $srcRoot = dirname(__DIR__, 2) . '/src';
        $file = $srcRoot . '/Application/Rehearsal/CreateRehearsalUseCase.php';

        $this->assertFileExists($file);
        $contents = file_get_contents($file);

        $this->assertStringContainsString('StageArt\Core\Contract\MembershipContract', $contents);
    }
}
