<?php

declare(strict_types=1);

namespace StageArt\Tests\Core;

use PHPUnit\Framework\TestCase;

/**
 * StageArt Core/Module Architecture Phase 2 (docs/architecture/
 * CoreModuleArchitecture.md §8): a structural guard, not a behavior
 * test - scans every Rehearsal and Accounting Module source file's own
 * `use` statements (not a naive substring search of the whole file, so
 * a docblock merely mentioning a forbidden class name does not trip
 * this) and fails if any of them imports one of Core's internal
 * Repository interfaces, Repository implementations, or Entities
 * directly, instead of going through `StageArt\Core\Contract\*`.
 *
 * Companion to RehearsalModuleDependencyDirectionTest (which checks the
 * narrower "no concrete Infrastructure import" rule) - this test checks
 * the specific classes Phase 2 §2/§8 name explicitly:
 * ProductionRepositoryInterface, ParticipantRepositoryInterface,
 * PersonRepositoryInterface, and the Production/Participant/Person
 * Entities themselves. `ProductionId`/`PersonId`/`OrganizationId` Value
 * Objects are allowed - a Module needs to hold and pass these ids, it
 * just must not resolve them into full Core Entities itself.
 *
 * Disclosed, intentionally excluded gap: JournalEntry\PostJournalEntryUseCase
 * still imports `Application\Organization\OrganizationAuthorizationService`
 * directly for its Organization-Scope (no Production) branch, since
 * `AuthorizationContract` is Production-scoped only and has no
 * Organization-scope Capability equivalent yet - see that file's own
 * docblock. Not included in the denylist below because it is a known,
 * disclosed remaining coupling, not something this test is meant to
 * catch by surprise.
 */
final class ModuleDependencyDirectionTest extends TestCase
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

    private const ACCOUNTING_MODULE_DIRECTORIES = [
        'Application/Account',
        'Application/Budget',
        'Application/Expense',
        'Application/JournalEntry',
        'Application/ProductionAccounting',
        'Domain/Account',
        'Domain/Budget',
        'Domain/Expense',
        'Domain/JournalEntry',
    ];

    private const FORBIDDEN_CORE_INTERNAL_IMPORTS = [
        'StageArt\Domain\Production\ProductionRepositoryInterface',
        'StageArt\Domain\Production\Production',
        'StageArt\Domain\Participant\ParticipantRepositoryInterface',
        'StageArt\Domain\Participant\Participant',
        'StageArt\Domain\Person\PersonRepositoryInterface',
        'StageArt\Domain\Person\Person',
        'StageArt\Application\Production\ProductionAuthorizationService',
    ];

    public function test_rehearsal_module_never_imports_core_internal_classes(): void
    {
        $this->assertNoForbiddenImports(self::REHEARSAL_MODULE_DIRECTORIES, 'Rehearsal');
    }

    public function test_accounting_module_never_imports_core_internal_classes(): void
    {
        $this->assertNoForbiddenImports(self::ACCOUNTING_MODULE_DIRECTORIES, 'Accounting');
    }

    /**
     * @param string[] $directories
     */
    private function assertNoForbiddenImports(array $directories, string $moduleName): void
    {
        $srcRoot = dirname(__DIR__, 2) . '/src';
        $violations = [];

        foreach ($directories as $relativeDir) {
            $dir = $srcRoot . '/' . $relativeDir;

            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.php') as $file) {
                $contents = file_get_contents($file);

                if (! preg_match_all('/^use\s+([^\s;]+);/m', $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $import) {
                    if (in_array($import, self::FORBIDDEN_CORE_INTERNAL_IMPORTS, true)) {
                        $violations[] = basename($file) . ' imports ' . $import;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "{$moduleName} Module files must not import Core's internal Repository/Entity classes directly - use StageArt\\Core\\Contract\\* instead:\n" . implode("\n", $violations)
        );
    }
}
