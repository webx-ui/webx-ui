<?php

declare(strict_types=1);

namespace WebxUi\Localization\Console;

use Illuminate\Console\Command;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Translations\DictionaryBuilder;

/**
 * Forget the cached language list and the panel's dictionaries.
 *
 * Needed after editing a `lang` file, because the dictionary the browser is handed is built
 * once and kept: without this the change is on disk, the panel still says the old thing, and
 * the obvious conclusion is that the edit went to the wrong file.
 */
final class ClearLocalesCommand extends Command
{
    protected $signature = 'webx:locales:clear';

    protected $description = 'Clear the cached languages and interface translations';

    public function handle(Locales $locales, DictionaryBuilder $dictionary): int
    {
        $dictionary->forget();
        $locales->forget();

        $this->components->info('Languages and interface translations will be read again.');

        return self::SUCCESS;
    }
}
