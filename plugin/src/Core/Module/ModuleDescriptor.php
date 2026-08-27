<?php

declare(strict_types=1);

namespace StageArt\Core\Module;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md §4): the minimal metadata a Domain
 * Module (Rehearsal today; Accounting/Ticket in the future) declares
 * about itself so Core - or, later, a genuinely separate StageArt Core
 * Plugin - can answer "is this Module present, is it compatible, and
 * what does it depend on" without importing that Module's internals to
 * find out.
 *
 * Deliberately minimal: this is metadata only, not a Plugin Loader. It
 * does not resolve dependencies, does not autodiscover Modules, and
 * does not manage activation state - a Module's actual wiring lives in
 * its own Bootstrap class (see `RehearsalModuleBootstrap`), constructed
 * and invoked explicitly by `Presentation\Plugin::boot()` today, and by
 * a future separate Plugin's own entry point once Rehearsal is
 * physically split out.
 */
interface ModuleDescriptor
{
    /**
     * A short, stable, lowercase identifier - e.g. "rehearsal". Used as
     * the ModuleRegistry's lookup key; never shown to end users.
     */
    public function moduleId(): string;

    /**
     * This Module's own version, independent of Core's
     * `STAGEART_VERSION` - a Module is expected to version itself once
     * it is a separately-releasable unit.
     */
    public function version(): string;

    /**
     * The minimum StageArt Core version this Module's code was written
     * against (a semver-style string, compared with `version_compare()`
     * by whoever wires the Module up). Not enforced automatically
     * anywhere yet - declaring it now is what lets a future check be
     * added without every Module needing to change shape.
     */
    public function requiredCoreVersion(): string;

    /**
     * Every `StageArt\Core\Contract\*` interface this Module's own
     * Application code depends on - the Module's own declaration of its
     * Core-facing boundary, cross-checked against
     * `ModuleDependencyDirectionTest` (which verifies the Module's real
     * `use` statements never reach past this list into Core's
     * internals).
     *
     * @return class-string[]
     */
    public function requiredContracts(): array;

    /**
     * Every database table (without the `$wpdb->prefix`) this Module
     * owns the schema and business rules for - see
     * `docs/architecture/CoreModuleArchitecture.md` §9's Database
     * Ownership table, which this list must stay consistent with.
     *
     * @return string[]
     */
    public function ownedTables(): array;
}
