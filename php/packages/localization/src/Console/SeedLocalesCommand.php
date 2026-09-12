<?php

declare(strict_types=1);

namespace WebxUi\Localization\Console;

use Illuminate\Console\Command;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;

/**
 * Put the configured languages into the table an installation actually reads.
 *
 * Seeding, not resetting: a language already there keeps whatever the panel has since done to
 * it — renamed, reordered, switched off.
 */
final class SeedLocalesCommand extends Command
{
    protected $signature = 'webx:locales:seed';

    protected $description = 'Create the languages named in config/webx-localization.php';

    public function handle(Locales $locales): int
    {
        $created = $locales->seed();

        $this->components->info($created === 0
            ? 'Every configured language is already there.'
            : "Added {$created} language(s).");

        $this->table(
            ['Code', 'Name', 'Native', 'Default'],
            Locale::query()->ordered()->get()->map(fn (Locale $locale): array => [
                $locale->code,
                $locale->name,
                $locale->native_name,
                $locale->is_default ? 'yes' : '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
