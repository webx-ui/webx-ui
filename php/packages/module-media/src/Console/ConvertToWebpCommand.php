<?php

declare(strict_types=1);

namespace WebxUi\Media\Console;

use Illuminate\Console\Command;
use Throwable;
use WebxUi\Media\Images\Optimizing\LibraryOptimizing;
use WebxUi\Media\Models\MediaFile;

/**
 * «Convert to WebP» for the whole library, or a folder of it, from the command line — what the
 * panel's dialog does ten at a time, without a browser left open.
 */
final class ConvertToWebpCommand extends Command
{
    protected $signature = 'webx:media:webp
        {--directory= : Only this folder (its id)}
        {--id=* : Only these files}
        {--dry-run : Say what would happen, references included, and change nothing}';

    protected $description = 'Convert the JPEG and PNG pictures of the library to WebP and rewrite every reference to them';

    public function handle(LibraryOptimizing $optimizing): int
    {
        $query = $optimizing->convertible()->orderBy('id');
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('id') !== []) {
            $query->whereIn('id', array_map('intval', (array) $this->option('id')));
        } elseif ($this->option('directory') !== null) {
            $query->where('directory_id', (int) $this->option('directory'));
        }

        $totals = ['converted' => 0, 'unchanged' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0];
        $saved = 0;
        $references = 0;

        // By id rather than by page: a converted file leaves the query, and an offset would skip.
        foreach ($query->lazyById(50) as $file) {
            /** @var MediaFile $file */
            try {
                $result = $optimizing->convert($file, $dryRun);
            } catch (Throwable $error) {
                report($error);
                $totals['failed']++;
                $this->error("#{$file->id} {$file->path}: {$error->getMessage()}");

                continue;
            }

            $totals[$result['status']] = ($totals[$result['status']] ?? 0) + 1;
            $saved += max(0, $result['before'] - $result['after']);
            $references += $result['references'];

            $this->line(sprintf(
                '#%d %s → %s: %s, %s → %s bytes, %d reference(s)',
                $file->id,
                $result['from'],
                $result['to'] ?? '—',
                $result['status'],
                $result['before'],
                $result['after'],
                $result['references'],
            ));
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would be: ' : '').collect($totals)->filter()->map(fn (int $count, string $status): string => "{$status} {$count}")->implode(', ') ?: 'Nothing to convert.');
        $this->info("Bytes saved: {$saved}; references rewritten: {$references}.");

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
