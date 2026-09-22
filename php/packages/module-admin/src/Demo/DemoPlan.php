<?php

declare(strict_types=1);

namespace WebxUi\Admin\Demo;

use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\ModuleRegistry;

/**
 * Who seeds, in what order, and who does not — worked out before anything is written.
 *
 * Navigation order is the starting point and not the answer: the panel puts pages second and
 * the library fifth, while a page made of blocks needs the block types first. So what a module
 * names in `requires()` decides the order too, and navigation only breaks the ties. A module
 * naming something that is not installed is left out with the reason said out loud — «blog
 * skipped: no media» — rather than failing in the middle of a seed.
 */
final class DemoPlan
{
    /**
     * @param  list<Module&ProvidesDemo>  $seed  In the order they run.
     * @param  array<string, list<string>>  $skipped  Module id => the ids it asked for and did not get.
     * @param  list<string>  $cyclic  Modules that require each other; seeded in navigation order.
     */
    private function __construct(
        public readonly array $seed,
        public readonly array $skipped,
        public readonly array $cyclic,
    ) {}

    public static function of(ModuleRegistry $registry): self
    {
        $providers = [];

        foreach ($registry->all() as $module) {
            if ($module instanceof ProvidesDemo) {
                $providers[$module->id()] = $module;
            }
        }

        $skipped = [];

        foreach ($providers as $id => $module) {
            $missing = array_values(array_filter(
                $module->requires(),
                static fn (string $required): bool => ! $registry->has($required),
            ));

            if ($missing !== []) {
                $skipped[$id] = $missing;
                unset($providers[$id]);
            }
        }

        [$ordered, $cyclic] = self::order($providers);

        return new self($ordered, $skipped, $cyclic);
    }

    /**
     * Requirements first, navigation order among equals.
     *
     * @param  array<string, Module&ProvidesDemo>  $providers
     * @return array{0: list<Module&ProvidesDemo>, 1: list<string>}
     */
    private static function order(array $providers): array
    {
        $ordered = [];
        $pending = $providers;

        while ($pending !== []) {
            $ready = null;

            foreach ($pending as $id => $module) {
                $waiting = array_filter(
                    $module->requires(),
                    static fn (string $required): bool => isset($pending[$required]),
                );

                if ($waiting === []) {
                    $ready = $id;

                    break;
                }
            }

            if ($ready === null) {
                // Two modules each waiting for the other. Nothing here can untie that, and
                // refusing to seed anything would be the worse answer: run them as the panel
                // lists them and say which ones they were.
                return [[...$ordered, ...array_values($pending)], array_keys($pending)];
            }

            $ordered[] = $pending[$ready];
            unset($pending[$ready]);
        }

        return [$ordered, []];
    }
}
