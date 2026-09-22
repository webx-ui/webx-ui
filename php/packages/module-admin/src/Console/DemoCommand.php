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
 */
final class DemoCommand extends Command
{
    protected $signature = 'webx:demo
                            {--remove : Take the demo content back out, following the journal backwards}
                            {--force : Seed again although a journal from an earlier run is there}';

    protected $description = 'Fill the site with demo content, or take it back out';

    public function handle(ModuleRegistry $registry, DemoLedger $ledger): int
    {
        return $this->option('remove') ? $this->remove($ledger) : $this->seed($registry, $ledger);
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
            ]);

            return self::FAILURE;
        }

        // Keep what the earlier run wrote down, so that one `--remove` still takes out both.
        $ledger->load();

        $plan = DemoPlan::of($registry);

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

        $this->newLine();
        $this->components->info('Demo content seeded. webx:demo --remove takes it back out.');

        return self::SUCCESS;
    }

    private function remove(DemoLedger $ledger): int
    {
        $ledger->load();

        if ($ledger->isEmpty()) {
            $this->components->info('Nothing to remove: no demo journal at '.$ledger->path().'.');

            return self::SUCCESS;
        }

        $entries = $ledger->entries();
        $kept = [];
        $removed = 0;

        // Backwards: what was created inside something else has to go before the something.
        foreach (array_reverse($entries, true) as $index => $entry) {
            try {
                $ledger->undo($entry);
                $removed++;
            } catch (Throwable $refused) {
                $kept[$index] = $entry;

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

            $this->components->error(sprintf(
                '%s removed, %d left in %s.',
                $this->items($removed),
                count($kept),
                $ledger->path(),
            ));

            return self::FAILURE;
        }

        $ledger->discard();

        // Not "the site is empty and works": a record that was filled in goes back to what it
        // held, and if the home page was an unpublished stub before the demo then that is what
        // it is again. Saying what happened beats promising what did not.
        $this->components->info($this->items($removed).' removed. What was there before the demo is back as it was.');

        return self::SUCCESS;
    }

    private function items(int $count): string
    {
        return $count.' item'.($count === 1 ? '' : 's');
    }
}
