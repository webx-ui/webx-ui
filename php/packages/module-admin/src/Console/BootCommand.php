<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Process\Process;
use Throwable;
use WebxUi\Admin\Boot\Plan;
use WebxUi\Admin\Boot\Step;

/**
 * Everything a container does between starting and serving its first request.
 *
 * `webx:setup` is for an empty directory and a person in front of it; this is for a process
 * that comes up on its own, with the site already decided and no one to ask. It waits for the
 * database, brings it up to the release, and leaves the site cached the way production runs.
 * Which steps those are is [`Boot\Plan`](../Boot/Plan.php), and it works them out from what is
 * installed — the reason this is a command in the package and not a script in the site.
 *
 * Every boot runs the whole sequence, so every step is idempotent by construction: the ones
 * that cannot tell "already done" from "failed" are marked non-fatal in the plan rather than
 * guessed at here.
 *
 * Each step is a child process. Not tidiness: `config:cache` changes where configuration comes
 * from for everything after it, and a step that fails should be a line rather than a stack
 * trace through the middle of the boot.
 */
final class BootCommand extends Command
{
    protected $signature = 'webx:boot
                            {--wait=120 : How long to wait for the database, in seconds; 0 does not wait at all}
                            {--no-cache : Leave configuration, routes, views and events uncached — for a container being developed in}
                            {--pretend : Print the steps and run none of them}';

    protected $description = 'Bring a container up: wait for the database, migrate, seed what travels as files, cache';

    public function handle(Plan $plan, DatabaseManager $db): int
    {
        $steps = $plan->steps(cache: ! $this->option('no-cache'));

        if ($this->option('pretend')) {
            $this->newLine();

            foreach ($steps as $step) {
                $this->components->twoColumnDetail($step->line(), "<fg=gray>{$step->why}</>");
            }

            $this->newLine();

            return self::SUCCESS;
        }

        if (! $this->waitForTheDatabase($db)) {
            return self::FAILURE;
        }

        foreach ($steps as $step) {
            if (! $this->runStep($step) && $step->fatal) {
                $this->components->error("{$step->what} failed, and nothing after it has run.");

                return self::FAILURE;
            }
        }

        $this->components->info('The site is up to date with this release.');

        return self::SUCCESS;
    }

    /**
     * Until the database answers, or until the patience runs out.
     *
     * Asked through the application's own connection rather than a bare PDO, so it is the
     * database this site is configured for and not merely a server on that port: on a first
     * boot the two are minutes apart, because the engine accepts connections for a while
     * before the volume it is initialising holds the schema this site was given.
     */
    private function waitForTheDatabase(DatabaseManager $db): bool
    {
        $seconds = max(0, (int) $this->option('wait'));

        if ($seconds === 0) {
            return true;
        }

        $name = $db->getDefaultConnection();
        $deadline = microtime(true) + $seconds;
        $said = false;

        while (true) {
            try {
                $db->connection($name)->getPdo();

                return true;
            } catch (Throwable $failure) {
                if (microtime(true) >= $deadline) {
                    $this->components->error(
                        "The database did not answer within {$seconds}s: ".$failure->getMessage()
                    );

                    return false;
                }

                if (! $said) {
                    $this->components->info("Waiting for the [{$name}] database…");
                    $said = true;
                }

                // Reconnecting is not enough — a connection that failed to open is cached as
                // a failure, and every attempt after it would be answered from that.
                $db->purge($name);
                sleep(1);
            }
        }
    }

    private function runStep(Step $step): bool
    {
        $this->components->twoColumnDetail($step->line(), "<fg=gray>{$step->why}</>");

        $process = new Process(
            [PHP_BINARY, $this->laravel->basePath('artisan'), ...$step->command],
            $this->laravel->basePath(),
            $step->env,
        );

        // No timeout: a first migration of every module's tables takes minutes on a small
        // machine, and a boot killed halfway leaves the site between two releases.
        $process->setTimeout(null);

        $status = $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if ($status !== 0 && ! $step->fatal) {
            $this->components->warn("{$step->what} exited with {$status}, which on a site where it has already run is what it does.");
        }

        return $status === 0;
    }
}
