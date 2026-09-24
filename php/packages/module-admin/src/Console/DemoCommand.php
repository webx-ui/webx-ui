<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Throwable;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoPlan;
use WebxUi\Admin\ModuleRegistry;

/**
 * Something to look at on a site nobody has written anything into yet — and one command to
 * take it all back out again.
 *
 * Every module brings its own: a module that has demo content implements {@see ProvidesDemo},
 * and this walks the ones that do. The order is theirs rather than the navigation's, because a
 * page made of blocks needs the block types first; a module whose requirements are not
 * installed is skipped with the reason said out loud.
 *
 * Removal is by journal and never by guesswork: what {@see DemoLedger} was told about is what
 * comes out, backwards, and what the site has written since stays. Afterwards the site is
 * working and empty — the layout is still files, the panel is still there, the database holds
 * structure and nothing else.
 *
 * `--module` narrows both to the modules named, because a site outlives its first seed: the
 * module installed next month lands on a site whose journal is already there, and "remove
 * everything and seed again" is not an answer to "show me the new section".
 */
final class DemoCommand extends Command
{
    protected $signature = 'webx:demo
                            {--module=* : Only these modules (repeatable), by id}
                            {--remove : Take the demo content back out, following the journal backwards}
                            {--force : Seed again although the journal already holds it}';

    protected $description = 'Fill the site with demo content, or take it back out';

    public function handle(ModuleRegistry $registry, DemoLedger $ledger): int
    {
        $only = $this->selected();

        if ($this->option('remove')) {
            return $only === [] ? $this->remove($ledger) : $this->removeModules($registry, $ledger, $only);
        }

        return $only === [] ? $this->seed($registry, $ledger) : $this->seedModules($registry, $ledger, $only);
    }

    private function seed(ModuleRegistry $registry, DemoLedger $ledger): int
    {
        if ($ledger->exists() && ! $this->option('force')) {
            $this->components->error(
                'Demo content is already installed, according to '.$ledger->path().'.',
            );
            $this->components->bulletList([
                'php artisan webx:demo --remove takes it out again.',
                'php artisan webx:demo --force seeds a second set beside it.',
                'php artisan webx:demo --module=<id> seeds a module that is not in it yet.',
            ]);

            return self::FAILURE;
        }

        // Keep what the earlier run wrote down, so that one `--remove` still takes out both.
        $ledger->load();

        $plan = DemoPlan::of($registry);
        $result = $this->seedPlan($plan, $ledger);

        if ($result === self::SUCCESS && $plan->seed !== []) {
            $this->newLine();
            $this->components->info('Demo content seeded. webx:demo --remove takes it back out.');
        }

        return $result;
    }

    /**
     * @param  list<string>  $only
     */
    private function seedModules(ModuleRegistry $registry, DemoLedger $ledger, array $only): int
    {
        $unknown = array_values(array_filter(
            $only,
            static fn (string $id): bool => ! $registry->has($id) || ! $registry->get($id) instanceof ProvidesDemo,
        ));

        if ($unknown !== []) {
            $this->components->error('No installed module with demo content is called '.implode(', ', $unknown).'.');
            $this->components->bulletList(['These have some: '.implode(', ', $this->providers($registry)).'.']);

            return self::FAILURE;
        }

        $ledger->load();

        $seeded = $ledger->modules();
        $again = array_values(array_intersect($only, $seeded));

        if ($again !== [] && ! $this->option('force')) {
            $this->components->error(sprintf(
                'Demo content of %s is already installed, according to %s.',
                implode(', ', $again),
                $ledger->path(),
            ));
            $this->components->bulletList([
                'php artisan webx:demo --remove '.$this->moduleOptions($again).' takes it out again.',
                'php artisan webx:demo --force '.$this->moduleOptions($again).' seeds a second set beside it.',
            ]);

            return self::FAILURE;
        }

        $plan = DemoPlan::of($registry)->only($only, $seeded);

        foreach ($plan->added as $id => $by) {
            $this->components->warn("{$id} added: {$by} requires it, and its demo is not seeded yet.");
        }

        $result = $this->seedPlan($plan, $ledger);

        if ($result === self::SUCCESS && $plan->seed !== []) {
            $ids = array_map(static fn ($module): string => $module->id(), $plan->seed);

            $this->newLine();
            $this->components->info('Demo content seeded. webx:demo --remove '.$this->moduleOptions($ids).' takes it back out.');
        }

        return $result;
    }

    private function seedPlan(DemoPlan $plan, DemoLedger $ledger): int
    {
        foreach ($plan->skipped as $id => $missing) {
            $this->components->warn(sprintf(
                '%s skipped: no %s.',
                $id,
                implode(', no ', $missing),
            ));
        }

        if ($plan->cyclic !== []) {
            $this->components->warn(
                'These modules require each other, so they are seeded in navigation order: '
                .implode(', ', $plan->cyclic).'.',
            );
        }

        if ($plan->seed === []) {
            $this->components->info('No installed module has demo content to seed.');

            return self::SUCCESS;
        }

        foreach ($plan->seed as $module) {
            $id = $module->id();
            $before = count($ledger->entries());

            $ledger->forModule($id);

            try {
                $module->seed($ledger);
            } catch (Throwable $failure) {
                // Written down first: whatever the module did manage to create is in the
                // journal, and `--remove` is what gets the site back to empty.
                $ledger->save();

                $this->components->error("{$id}: {$failure->getMessage()}");
                $this->components->warn('What was created up to here is in '.$ledger->path().'; webx:demo --remove takes it out.');

                return self::FAILURE;
            }

            $written = count($ledger->entries()) - $before;
            $ledger->save();

            $this->components->twoColumnDetail($id, $written === 0 ? 'nothing to seed' : $this->items($written));

            foreach ($ledger->takeNotes() as $note) {
                $this->components->warn("{$id}: {$note}");
            }
        }

        return self::SUCCESS;
    }

    private function remove(DemoLedger $ledger): int
    {
        $ledger->load();

        if ($ledger->isEmpty()) {
            $this->components->info('Nothing to remove: no demo journal at '.$ledger->path().'.');

            return self::SUCCESS;
        }

        return $this->undo($ledger, null);
    }

    /**
     * @param  list<string>  $only
     */
    private function removeModules(ModuleRegistry $registry, DemoLedger $ledger, array $only): int
    {
        $ledger->load();

        $seeded = $ledger->modules();
        $unknown = array_values(array_filter(
            $only,
            static fn (string $id): bool => ! in_array($id, $seeded, true) && ! $registry->has($id),
        ));

        if ($unknown !== []) {
            $this->components->error('No module is called '.implode(', ', $unknown).'.');

            return self::FAILURE;
        }

        $removing = array_values(array_intersect($only, $seeded));

        foreach (array_diff($only, $seeded) as $id) {
            $this->components->info("Nothing to remove for {$id}: the journal holds none of its demo.");
        }

        if ($removing === []) {
            return self::SUCCESS;
        }

        $dependants = DemoPlan::dependants($registry, $removing, $seeded);

        if ($dependants !== []) {
            foreach ($dependants as $id => $required) {
                $this->components->error("{$id} requires {$required}, and its demo is still seeded.");
            }

            $this->components->bulletList([
                'php artisan webx:demo --remove '.$this->moduleOptions([...array_keys($dependants), ...$removing]).' takes them out together.',
            ]);

            return self::FAILURE;
        }

        return $this->undo($ledger, $removing);
    }

    /**
     * Play the journal backwards — the whole of it, or only these modules' part.
     *
     * @param  list<string>|null  $only
     */
    private function undo(DemoLedger $ledger, ?array $only): int
    {
        $entries = $ledger->entries();
        $kept = [];
        $removed = 0;
        $failed = 0;

        // Backwards: what was created inside something else has to go before the something.
        foreach (array_reverse($entries, true) as $index => $entry) {
            if ($only !== null && ! in_array($entry['module'] ?? null, $only, true)) {
                $kept[$index] = $entry;

                continue;
            }

            try {
                $ledger->undo($entry);
                $removed++;
            } catch (Throwable $refused) {
                $kept[$index] = $entry;
                $failed++;

                $this->components->warn(sprintf(
                    '%s kept: %s',
                    $entry['label'] ?? 'an entry',
                    $refused->getMessage(),
                ));
            }
        }

        if ($kept !== []) {
            // In the order they were written, so a second attempt still runs backwards.
            ksort($kept);
            $ledger->retain(array_values($kept));
            $ledger->save();
        } else {
            $ledger->discard();
        }

        if ($failed > 0) {
            $this->components->error(sprintf(
                '%s removed, %d left in %s.',
                $this->items($removed),
                $failed,
                $ledger->path(),
            ));

            return self::FAILURE;
        }

        // Not "the site is empty and works": a record that was filled in goes back to what it
        // held, and if the home page was an unpublished stub before the demo then that is what
        // it is again. Saying what happened beats promising what did not.
        $this->components->info($this->items($removed).' removed. What was there before the demo is back as it was.');

        return self::SUCCESS;
    }

    /**
     * `--module` as given, trimmed and without repeats; empty means every module, as before.
     *
     * @return list<string>
     */
    private function selected(): array
    {
        $option = $this->option('module');
        $ids = [];

        foreach (is_array($option) ? $option : [] as $id) {
            $id = trim((string) $id);

            if ($id !== '' && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function providers(ModuleRegistry $registry): array
    {
        $ids = [];

        foreach ($registry->all() as $module) {
            if ($module instanceof ProvidesDemo) {
                $ids[] = $module->id();
            }
        }

        return $ids;
    }

    /**
     * @param  list<string>  $ids
     */
    private function moduleOptions(array $ids): string
    {
        return implode(' ', array_map(static fn (string $id): string => '--module='.$id, $ids));
    }

    private function items(int $count): string
    {
        return $count.' item'.($count === 1 ? '' : 's');
    }
}
