<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Checks\Halves;
use WebxUi\Admin\Doctor\Checks\Languages;
use WebxUi\Admin\Doctor\Checks\Layouts;
use WebxUi\Admin\Doctor\Checks\Migrations;
use WebxUi\Admin\Doctor\Checks\NpmRanges;
use WebxUi\Admin\Doctor\Checks\PanelOpens;
use WebxUi\Admin\Doctor\Checks\PassportKeys;
use WebxUi\Admin\Doctor\Checks\SiteGate;
use WebxUi\Admin\Doctor\Checks\Storage;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * Whether this site is actually standing up.
 *
 * The last step of `webx:setup` and the last step of a deploy, and the same command both times.
 * Everything it looks at is something that has cost somebody a day: a bundle built before the
 * module was installed, a layout without `@stack('head')`, an npm half a minor version too old,
 * a migration that MariaDB refused, a cache written before the file it caches, a named limiter
 * nobody declared. None of them announce themselves — that is what they have in common, and
 * why they are worth a command.
 *
 * It repairs nothing. A site that is deploying badly is a bad place to start putting things
 * right by guesswork, so every line says what to run and leaves the running to a person. The
 * one thing it writes is a probe file on the library's disk, which it deletes again — asking
 * whether a disk is writable any other way is asking something else.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'webx:doctor
                            {--strict : Treat anything worth mentioning as a failure — for a deploy that should stop}';

    protected $description = 'Check that this site is set up the way it has to be: both halves, storage, layout, migrations, caches';

    /**
     * In the order somebody reads them: what the site is made of, then what it needs to run.
     *
     * @var list<class-string<Check>>
     */
    private const CHECKS = [
        Halves::class,
        NpmRanges::class,
        Migrations::class,
        Storage::class,
        Layouts::class,
        Languages::class,
        PanelOpens::class,
        PassportKeys::class,
        SiteGate::class,
    ];

    public function handle(Container $container): int
    {
        $this->newLine();

        $failed = 0;
        $warned = 0;

        foreach (self::CHECKS as $class) {
            foreach ($this->diagnose($container, $class) as $diagnosis) {
                $this->say($diagnosis);

                $failed += $diagnosis->failed() ? 1 : 0;
                $warned += $diagnosis->warned() ? 1 : 0;
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->components->error($failed.' of the things this site needs '.($failed === 1 ? 'is' : 'are').' not in place.');

            return self::FAILURE;
        }

        if ($warned > 0 && $this->option('strict')) {
            $this->components->error($warned.' worth mentioning, and --strict says that is enough to stop.');

            return self::FAILURE;
        }

        $this->components->info(
            $warned > 0 ? "Nothing is broken; {$warned} worth knowing about." : 'Everything this site needs is in place.',
        );

        return self::SUCCESS;
    }

    /**
     * Run one check, and let a check that breaks be a line rather than a stack trace.
     *
     * A doctor that dies halfway through hides every check after it, which on a bad deploy is
     * exactly the half somebody needed.
     *
     * @param  class-string<Check>  $class
     * @return list<Diagnosis>
     */
    private function diagnose(Container $container, string $class): array
    {
        try {
            return $container->make($class)->run();
        } catch (Throwable $failure) {
            return [Diagnosis::fail(
                class_basename($class),
                'this check could not run: '.$failure->getMessage(),
            )];
        }
    }

    private function say(Diagnosis $diagnosis): void
    {
        $mark = match ($diagnosis->state) {
            Diagnosis::OK => '<fg=green>✓</>',
            Diagnosis::WARN => '<fg=yellow>!</>',
            default => '<fg=red>✗</>',
        };

        // One line each, written out rather than laid out in two columns: what a check has to
        // say is a sentence with a command in it, and a column would either wrap it or cut it.
        $this->line("  {$mark} <options=bold>{$diagnosis->subject}</>: {$diagnosis->detail}");
    }
}
