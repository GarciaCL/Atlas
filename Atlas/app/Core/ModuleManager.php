<?php

namespace Atlas\Core;

use Atlas\Contracts\ModuleInterface;

class ModuleManager
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    public function register(string $name, ModuleInterface $module): void
    {
        if (!isset($this->modules[$name])) {
            $module->register();
            $this->modules[$name] = $module;
        }
    }

    public function bootModules(): void
    {
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    public function get(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }
}