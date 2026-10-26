<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Checks\CacheStore;
use WebxUi\Admin\Doctor\Checks\Halves;
use WebxUi\Admin\Doctor\Checks\Helpers;
use WebxUi\Admin\Doctor\Checks\Languages;
use WebxUi\Admin\Doctor\Checks\Layouts;
use WebxUi\Admin\Doctor\Checks\Mail;
use WebxUi\Admin\Doctor\Checks\Methods;
use WebxUi\Admin\Doctor\Checks\Migrations;
use WebxUi\Admin\Doctor\Checks\NpmRanges;
use WebxUi\Admin\Doctor\Checks\PanelOpens;
use WebxUi\Admin\Doctor\Checks\PassportKeys;
use WebxUi\Admin\Doctor\Checks\Queue;
use WebxUi\Admin\Doctor\Checks\Regions;
use WebxUi\Admin\Doctor\Checks\Relations;
use WebxUi\Admin\Doctor\Checks\SiteGate;
use WebxUi\Admin\Doctor\Checks\Snapshots;
use WebxUi\Admin\Doctor\Checks\Storage;
use WebxUi\Admin\Doctor\Checks\StorageOwner;
use WebxUi\Admin\Doctor\Checks\TrustedProxies;
use WebxUi\Admin\Doctor\Checks\UploadSpace;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;

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
                            {--strict : Treat a warning about the setup as a failure — for a deploy that should stop; notes, and notes on the content, never stop it}
                            {--json : Print the findings as JSON, for a platform that decides by them}';

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
        StorageOwner::class,
        UploadSpace::class,
        Snapshots::class,
        Layouts::class,
        Regions::class,
        Helpers::class,
        Relations::class,
        Languages::class,
        PanelOpens::class,
        Methods::class,
        PassportKeys::class,
        SiteGate::class,
        TrustedProxies::class,
        CacheStore::class,
        Queue::class,
        Mail::class,
    ];

    public function handle(Container $container): int
    {
        $json = (bool) $this->option('json');

        if (! $json) {
            $this->newLine();
        }

        $checks = [];
        $failed = 0;
        $warned = 0;
        $content = 0;
        $notes = 0;
        $stops = false;

        foreach ([...self::CHECKS, ...$container->make(DoctorChecks::class)->all()] as $class) {
            foreach ($this->diagnose($container, $class) as $diagnosis) {
                if ($json) {
                    $checks[] = $diagnosis;
                } else {
                    $this->say($diagnosis);
                }

                $failed += $diagnosis->failed() ? 1 : 0;
                $warned += $diagnosis->level() === Diagnosis::WARN ? 1 : 0;
                $content += $diagnosis->level() === 'content' ? 1 : 0;
                $notes += $diagnosis->level() === 'note' ? 1 : 0;
                $stops = $stops || $diagnosis->stops((bool) $this->option('strict'));
            }
        }

        if ($json) {
            // For a platform that decides by it: `warned`, `content` and `notes` never count the
            // same line twice, so `failed + warned > 0` is exactly what --strict stops on, and
            // `ok` is the exit code said in the report itself.
            $this->line((string) json_encode([
                'ok' => ! $stops,
                'checks' => array_map(static fn (Diagnosis $diagnosis): array => [
                    'state' => $diagnosis->state,
                    'level' => $diagnosis->level(),
                    'subject' => $diagnosis->subject,
                    'detail' => $diagnosis->detail,
                    'content' => $diagnosis->content,
                ], $checks),
                'failed' => $failed,
                'warned' => $warned,
                'content' => $content,
                'notes' => $notes,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $stops ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();

        $tail = ($notes > 0 ? " {$notes} ".($notes === 1 ? 'note' : 'notes').' to know about.' : '')
            .($content > 0 ? " {$content} ".($content === 1 ? 'note' : 'notes').' on the content — mended in the panel.' : '');

        if ($failed > 0) {
            $this->components->error($failed.' of the things this site needs '.($failed === 1 ? 'is' : 'are').' not in place.'.$tail);

            return self::FAILURE;
        }

        if ($stops) {
            $this->components->error($warned.' worth mentioning, and --strict says that is enough to stop.'.$tail);

            return self::FAILURE;
        }

        $this->components->info(match (true) {
            $warned > 0 => "Nothing is broken; {$warned} worth knowing about.".$tail,
            default => 'Everything this site needs is in place.'.$tail,
        });

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
            Diagnosis::WARN => $diagnosis->note ? '<fg=cyan>i</>' : '<fg=yellow>!</>',
            default => '<fg=red>✗</>',
        };

        // One line each, written out rather than laid out in two columns: what a check has to
        // say is a sentence with a command in it, and a column would either wrap it or cut it.
        $kind = match ($diagnosis->level()) {
            'content' => ' <fg=gray>(content)</>',
            'note' => ' <fg=gray>(note)</>',
            default => '',
        };

        $this->line("  {$mark}{$kind} <options=bold>{$diagnosis->subject}</>: {$diagnosis->detail}");
    }
}
