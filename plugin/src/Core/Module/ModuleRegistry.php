<?php

declare(strict_types=1);

namespace StageArt\Core\Module;

/**
 * StageArt Core/Module Architecture Phase 3: a plain in-memory
 * collection of registered `ModuleDescriptor`s - deliberately not a
 * Plugin Loader (no autodiscovery, no dependency resolution, no
 * activation-state management). Its entire job is to let Core answer
 * "which Modules are present" without hardcoding a Module's name into
 * Core code, and to give `docs/architecture/WordPressPluginModuleBoundary.md`'s
 * "Coreだけでも動作する" claim something concrete to test against:
 * Core boots and this Registry simply stays empty if no Module
 * registers into it (see `CoreOnlyBootstrapTest`).
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleDescriptor> */
    private array $modules = [];

    public function register(ModuleDescriptor $module): void
    {
        $this->modules[$module->moduleId()] = $module;
    }

    public function isRegistered(string $moduleId): bool
    {
        return isset($this->modules[$moduleId]);
    }

    public function get(string $moduleId): ?ModuleDescriptor
    {
        return $this->modules[$moduleId] ?? null;
    }

    /**
     * @return ModuleDescriptor[]
     */
    public function all(): array
    {
        return array_values($this->modules);
    }
}
