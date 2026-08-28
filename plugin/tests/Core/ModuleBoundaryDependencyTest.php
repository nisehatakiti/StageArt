<?php

declare(strict_types=1);

namespace StageArt\Tests\Core;

use PHPUnit\Framework\TestCase;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md §12): extends
 * `ModuleDependencyDirectionTest` (Module -> Core Internals: NG) with
 * the two directions Phase 2 never checked - sibling Module -> sibling
 * Module, and Core -> Module Domain. All four checks scan real `use`
 * statements (not a naive whole-file string search), so a docblock
 * mentioning another Module's class name in prose does not trip these.
 *
 * If any of these ever fail, it means a change introduced a dependency
 * that would break if Rehearsal/Accounting were physically split into
 * separate WordPress Plugins - exactly what
 * `docs/architecture/WordPressPluginModuleBoundary.md` is trying to
 * keep true going forward, not just at the moment this test was
 * written.
 */
final class ModuleBoundaryDependencyTest extends TestCase
{
    private const REHEARSAL_MODULE_DIRECTORIES = [
        'Application/Rehearsal',
        'Application/RehearsalAttendance',
        'Application/Timetable',
        'Application/TimetableItem',
        'Application/ScheduleComment',
        'Application/PrintView',
        'Application/ProductionSchedule',
        'Domain/Rehearsal',
        'Domain/RehearsalAttendance',
        'Domain/Timetable',
        'Domain/TimetableItem',
        'Domain/ScheduleComment',
        'Rehearsal',
    ];

    private const ACCOUNTING_MODULE_DIRECTORIES = [
        'Application/Account',
        'Application/Accounting',
        'Application/Budget',
        'Application/Expense',
        'Application/JournalEntry',
        'Application/ProductionAccounting',
        'Domain/Account',
        'Domain/Budget',
        'Domain/Expense',
        'Domain/JournalEntry',
        'Accounting',
    ];

    private const REHEARSAL_NAMESPACE_PREFIXES = [
        'StageArt\Application\Rehearsal\\',
        'StageArt\Application\RehearsalAttendance\\',
        'StageArt\Application\Timetable\\',
        'StageArt\Application\TimetableItem\\',
        'StageArt\Application\ScheduleComment\\',
        'StageArt\Application\PrintView\\',
        'StageArt\Application\ProductionSchedule\\',
        'StageArt\Domain\Rehearsal\\',
        'StageArt\Domain\RehearsalAttendance\\',
        'StageArt\Domain\Timetable\\',
        'StageArt\Domain\TimetableItem\\',
        'StageArt\Domain\ScheduleComment\\',
        'StageArt\Rehearsal\\',
    ];

    private const ACCOUNTING_NAMESPACE_PREFIXES = [
        'StageArt\Application\Account\\',
        'StageArt\Application\Accounting\\',
        'StageArt\Application\Budget\\',
        'StageArt\Application\Expense\\',
        'StageArt\Application\JournalEntry\\',
        'StageArt\Application\ProductionAccounting\\',
        'StageArt\Domain\Account\\',
        'StageArt\Domain\Budget\\',
        'StageArt\Domain\Expense\\',
        'StageArt\Domain\JournalEntry\\',
        'StageArt\Accounting\\',
    ];

    public function test_rehearsal_module_never_imports_accounting(): void
    {
        $this->assertNoImportsFromNamespaces(
            self::REHEARSAL_MODULE_DIRECTORIES,
            self::ACCOUNTING_NAMESPACE_PREFIXES,
            'Rehearsal',
            'Accounting'
        );
    }

    public function test_accounting_module_never_imports_rehearsal(): void
    {
        $this->assertNoImportsFromNamespaces(
            self::ACCOUNTING_MODULE_DIRECTORIES,
            self::REHEARSAL_NAMESPACE_PREFIXES,
            'Accounting',
            'Rehearsal'
        );
    }

    /**
     * Scoped to `src/Core/` specifically (Contract + Adapter + Module
     * primitives) - the literal Core boundary layer Phase 1-3 built, not
     * every file outside the Rehearsal/Accounting directories. Core's
     * own cross-Module aggregation UseCases (`Application\Dashboard`,
     * `Application\Notification`'s feed-listing UseCases) are a
     * separate, disclosed, NOT-yet-fixed coupling - see
     * `docs/architecture/WordPressPluginModuleBoundary.md`'s Known
     * Remaining Coupling section for why this test does not (yet) cover
     * them too.
     */
    public function test_core_never_imports_rehearsal_or_accounting_domain(): void
    {
        $srcRoot = dirname(__DIR__, 2) . '/src';
        $coreDir = $srcRoot . '/Core';
        $violations = [];

        $forbidden = array_merge(self::REHEARSAL_NAMESPACE_PREFIXES, self::ACCOUNTING_NAMESPACE_PREFIXES);

        foreach ($this->phpFilesRecursive($coreDir) as $file) {
            $contents = file_get_contents($file);

            if (! preg_match_all('/^use\s+([^\s;]+);/m', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $import) {
                foreach ($forbidden as $prefix) {
                    if (str_starts_with($import, $prefix)) {
                        $violations[] = basename($file) . ' imports ' . $import;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "src/Core must not import Rehearsal/Accounting Domain classes:\n" . implode("\n", $violations)
        );
    }

    public function test_accounting_module_uses_at_least_one_core_contract(): void
    {
        $srcRoot = dirname(__DIR__, 2) . '/src';
        $file = $srcRoot . '/Application/Budget/CreateBudgetUseCase.php';

        $this->assertFileExists($file);
        $contents = file_get_contents($file);

        $this->assertStringContainsString('StageArt\Core\Contract\ProductionContextContract', $contents);
    }

    /**
     * @param string[] $directories
     * @param string[] $forbiddenPrefixes
     */
    private function assertNoImportsFromNamespaces(
        array $directories,
        array $forbiddenPrefixes,
        string $moduleName,
        string $forbiddenModuleName
    ): void {
        $srcRoot = dirname(__DIR__, 2) . '/src';
        $violations = [];

        foreach ($directories as $relativeDir) {
            $dir = $srcRoot . '/' . $relativeDir;

            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFilesRecursive($dir) as $file) {
                $contents = file_get_contents($file);

                if (! preg_match_all('/^use\s+([^\s;]+);/m', $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $import) {
                    foreach ($forbiddenPrefixes as $prefix) {
                        if (str_starts_with($import, $prefix)) {
                            $violations[] = basename($file) . ' imports ' . $import;
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "{$moduleName} Module files must not import {$forbiddenModuleName} Module classes directly:\n" . implode("\n", $violations)
        );
    }

    /**
     * @return string[]
     */
    private function phpFilesRecursive(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];

        foreach (glob($dir . '/*.php') as $file) {
            $files[] = $file;
        }

        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $subdir) {
            $files = array_merge($files, $this->phpFilesRecursive($subdir));
        }

        return $files;
    }
}
