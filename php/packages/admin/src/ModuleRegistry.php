<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\Exceptions\ModuleException;

/**
 * Everything the panel is made of.
 *
 * Modules register themselves from their own service providers, so the set is whatever the
 * application has installed. Registration is strict about ids: two modules answering to the
 * same name would collide in URLs, in permissions and in the manifest, and the failure would
 * surface far from its cause.
 */
final class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function register(Module $module): void
    {
        $id = $module->id();

        if ($id === '') {
            throw ModuleException::emptyId($module::class);
        }

        if (isset($this->modules[$id])) {
            throw ModuleException::duplicate($id);
        }

        $this->modules[$id] = $module;
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    public function get(string $id): Module
    {
        return $this->modules[$id] ?? throw ModuleException::unknown($id);
    }

    /**
     * Registered modules in navigation order.
     *
     * @return list<Module>
     */
    public function all(): array
    {
        $modules = array_values($this->modules);

        usort(
            $modules,
            static fn (Module $a, Module $b): int => [$a->order(), $a->id()] <=> [$b->order(), $b->id()],
        );

        return $modules;
    }

    public function count(): int
    {
        return count($this->modules);
    }
}
