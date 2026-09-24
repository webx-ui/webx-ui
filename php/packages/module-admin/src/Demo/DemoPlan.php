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
     * @param  array<string, string>  $added  Module id => the selected one that pulled it in; see {@see only()}.
     */
    private function __construct(
        public readonly array $seed,
        public readonly array $skipped,
        public readonly array $cyclic,
        public readonly array $added = [],
    ) {}

    /**
     * The same plan narrowed to the modules asked for — a module installed on a site that was
     * seeded before it existed, without a second set of everything else.
     *
     * What they require comes along when it has demo content and the journal holds none of it.
     * An article's cover is looked up in the library's part of the journal, so a module seeded
     * without its requirement is exactly the half-seed `requires()` is there to prevent — and
     * refusing instead would only print the command that does this. What gets pulled in is
     * said out loud through `$added`; a requirement that is already seeded is left alone.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $seeded  The modules the journal already holds.
     */
    public function only(array $ids, array $seeded): self
    {
        $byId = [];

        foreach ($this->seed as $module) {
            $byId[$module->id()] = $module;
        }

        $wanted = array_fill_keys($ids, true);
        $added = [];
        $queue = $ids;

        while ($queue !== []) {
            $id = array_shift($queue);

            foreach (isset($byId[$id]) ? $byId[$id]->requires() : [] as $required) {
                if (isset($wanted[$required]) || ! isset($byId[$required]) || in_array($required, $seeded, true)) {
                    continue;
                }

                $wanted[$required] = true;
                $added[$required] = $id;
                $queue[] = $required;
            }
        }

        return new self(
            array_values(array_filter($this->seed, static fn (Module $module): bool => isset($wanted[$module->id()]))),
            array_intersect_key($this->skipped, $wanted),
            array_values(array_filter($this->cyclic, static fn (string $id): bool => isset($wanted[$id]))),
            $added,
        );
    }

    /**
     * Seeded modules that still need one of these — what refuses a removal by module.
     *
     * Taking the library out from under the blog would leave covers pointing at files that are
     * gone while the journal still says the blog is seeded. So the answer is the whole chain,
     * dependants of dependants included, and the command names it as one command.
     *
     * @param  list<string>  $removing
     * @param  list<string>  $seeded  The modules the journal holds.
     * @return array<string, string> Seeded module id => the module it requires out of the chain.
     */
    public static function dependants(ModuleRegistry $registry, array $removing, array $seeded): array
    {
        $gone = array_fill_keys($removing, true);
        $dependants = [];

        do {
            $found = false;

            foreach ($seeded as $id) {
                if (isset($gone[$id]) || ! $registry->has($id)) {
                    continue;
                }

                $module = $registry->get($id);

                if (! $module instanceof ProvidesDemo) {
                    continue;
                }

                foreach ($module->requires() as $required) {
                    if (isset($gone[$required])) {
                        $dependants[$id] = $required;
                        $gone[$id] = true;
                        $found = true;

                        break;
                    }
                }
            }
        } while ($found);

        return $dependants;
    }

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
