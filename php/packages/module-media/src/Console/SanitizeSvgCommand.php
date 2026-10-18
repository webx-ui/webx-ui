<?php

declare(strict_types=1);

namespace WebxUi\Media\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Support\FileTypes;
use WebxUi\Media\Support\SvgSanitizer;

/**
 * The SVGs uploaded before the library sanitized them, put through the sanitizer now.
 *
 * A file that loses something is written back under the same key — every page that shows it
 * keeps showing it — with its hash, and so the version in its address, moved on, so a CDN lets go
 * of the old bytes. Each line says what went, which is what a person reads to tell a stripped
 * tracking pixel from a stripped attack.
 *
 * Also listed, and left alone: SVGs that are not a well-formed drawing (a browser shows them as
 * an XML error, but nobody should keep them), and files kept under an extension the library no
 * longer accepts — before types were read from the bytes, the extension was the client's.
 */
final class SanitizeSvgCommand extends Command
{
    protected $signature = 'webx:media:sanitize-svg
        {--id=* : Only these files}
        {--dry-run : Say what would be removed and write nothing}';

    protected $description = 'Sanitize the SVG files already in the media library and report what was removed';

    public function handle(SvgSanitizer $sanitizer, FileStore $store, FileTypes $types): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_map('intval', (array) $this->option('id'));
        $totals = ['sanitized' => 0, 'clean' => 0, 'refused' => 0, 'missing' => 0, 'failed' => 0];

        $query = MediaFile::query()
            ->where(fn ($query) => $query->where('extension', 'svg')->orWhere('mime', 'like', '%svg%'))
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids));

        foreach ($query->lazyById(50) as $file) {
            /** @var MediaFile $file */
            try {
                $status = $this->sanitize($file, $sanitizer, $store, $dryRun);
            } catch (Throwable $error) {
                report($error);
                $status = 'failed';
                $this->error("#{$file->id} {$file->path}: {$error->getMessage()}");
            }

            $totals[$status]++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would be: ' : '').(collect($totals)->filter()->map(fn (int $count, string $status): string => "{$status} {$count}")->implode(', ') ?: 'No SVG files in the library.'));

        $this->reportForeign($types, $ids);

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return 'sanitized'|'clean'|'refused'|'missing'
     */
    private function sanitize(MediaFile $file, SvgSanitizer $sanitizer, FileStore $store, bool $dryRun): string
    {
        $disk = $store->disk($file->disk);

        if (! $disk->exists($file->path)) {
            $this->warn("#{$file->id} {$file->path}: missing on the disk");

            return 'missing';
        }

        $result = $sanitizer->sanitize((string) $disk->get($file->path));

        if ($result === null) {
            $this->warn("#{$file->id} {$file->path}: not a well-formed SVG, left as it is — delete or replace it");

            return 'refused';
        }

        // A type recorded from the client's header is put right even when the drawing is clean.
        $mimeWrong = $file->mime !== FileTypes::SVG;

        if (! $result->changed() && ! $mimeWrong) {
            return 'clean';
        }

        // Escaped: `<script>` is a tag to the console's formatter, and would vanish from the report.
        $this->line(OutputFormatter::escape(sprintf(
            '#%d %s: %s',
            $file->id,
            $file->path,
            $result->changed() ? 'removed '.$this->summary($result->removed) : "type {$file->mime} → ".FileTypes::SVG,
        )));

        if ($dryRun) {
            return $result->changed() ? 'sanitized' : 'clean';
        }

        if ($result->changed()) {
            $disk->put($file->path, $result->contents);
        }

        $file->update([
            'mime' => FileTypes::SVG,
            'hash' => $result->changed() ? md5($result->contents) : $file->hash,
            'size' => $result->changed() ? strlen($result->contents) : $file->size,
        ]);

        return $result->changed() ? 'sanitized' : 'clean';
    }

    /**
     * `<script>, <rect onclick> ×2` — each thing once, with how many times it went.
     *
     * @param  list<string>  $removed
     */
    private function summary(array $removed): string
    {
        return collect(array_count_values($removed))
            ->map(fn (int $count, string $what): string => $count > 1 ? "{$what} ×{$count}" : $what)
            ->implode(', ');
    }

    /**
     * @param  list<int>  $ids
     */
    private function reportForeign(FileTypes $types, array $ids): void
    {
        $foreign = MediaFile::query()
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->select(['id', 'path', 'extension'])
            ->lazyById(200)
            ->reject(fn (MediaFile $file): bool => $types->allowed($file->extension));

        foreach ($foreign as $file) {
            /** @var MediaFile $file */
            $this->warn("#{$file->id} {$file->path}: kept as .{$file->extension}, which the library does not accept — check it and delete or re-upload it");
        }
    }
}
