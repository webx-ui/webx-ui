<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * What the installed modules offer as block types, and which of it this site already has
 * (§3.4 of the FAQ spec). `--install` takes what is missing and publishes it; a type by the same
 * slug is left alone whatever it looks like now.
 *
 * `webx:setup` runs this with `--install` for the modules somebody chose. On a site that adds a
 * module to a panel it already has, it is the one line in the module's README.
 */
final class OfferedCommand extends Command
{
    protected $signature = 'webx:blocks:offered
        {--module=* : Only what these modules offer, by their id in the panel}
        {--install : Install and publish the types this site does not have yet}';

    protected $description = 'List the block types the installed modules offer, and install the missing ones';

    public function handle(BlockOffers $offers): int
    {
        /** @var list<string> $modules */
        $modules = array_values(array_filter((array) $this->option('module'), static fn (mixed $one): bool => is_string($one) && $one !== ''));
        $install = (bool) $this->option('install');

        try {
            $offered = $offers->documents($modules === [] ? null : $modules);
        } catch (BlocksException $broken) {
            $this->components->error($broken->getMessage());

            return self::FAILURE;
        }

        if ($offered === []) {
            $this->components->info($modules === [] ? 'No module offers block types.' : 'These modules offer no block types.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($offered as $offer) {
            $label = "{$offer['module']} · {$offer['slug']}";

            if (! $install) {
                $this->components->twoColumnDetail($label, $offers->present($offer['slug']) ? 'on the site' : 'not installed');

                continue;
            }

            try {
                $status = $offers->install($offer);
            } catch (BlocksException $refused) {
                $this->components->error($refused->getMessage());
                $failed = true;

                continue;
            }

            if ($status === BlockOffers::DRAFT) {
                $failed = true;
            }

            $this->components->twoColumnDetail($label, match ($status) {
                BlockOffers::INSTALLED => 'installed and published',
                BlockOffers::DRAFT => 'installed as a draft — it does not render on its sample; open it in the panel',
                default => 'on the site — left alone',
            });
        }

        if (! $install && ! $failed) {
            $this->components->info('`--install` takes what is missing; a type already on the site is never touched.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
