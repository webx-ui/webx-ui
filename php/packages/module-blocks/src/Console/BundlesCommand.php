<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Blocks\Models\BlockBundle;
use WebxUi\Blocks\Rendering\Bundles;

/**
 * Housekeeping for the bundles of styles and scripts.
 *
 * `--prune` drops the bundles glued from versions that are no longer published: nothing renders
 * them, only HTML cached outside the application could still name one. `--warm` writes the
 * bundle of every entity of the models listed in `webx-blocks.entities`, so the first visitor
 * after a deploy or a prune is not the one who pays for it.
 */
final class BundlesCommand extends Command
{
    protected $signature = 'webx:blocks:bundles
        {--prune : Drop the bundles glued from versions that are no longer published}
        {--warm : Build the bundle of every entity of the models listed in webx-blocks.entities}';

    protected $description = 'Prune or warm the bundles of block styles and scripts';

    public function handle(Bundles $bundles, Config $config): int
    {
        $prune = (bool) $this->option('prune');
        $warm = (bool) $this->option('warm');

        if (! $prune && ! $warm) {
            $this->line(sprintf('%d bundle(s) stored. Use --prune to drop the stale ones, --warm to build the ones the entities need.', BlockBundle::query()->count()));

            return self::SUCCESS;
        }

        if ($prune) {
            $this->info(sprintf('Pruned %d bundle(s).', $bundles->prune()));
        }

        if ($warm) {
            return $this->warm($bundles, $config);
        }

        return self::SUCCESS;
    }

    private function warm(Bundles $bundles, Config $config): int
    {
        $models = $config->get('webx-blocks.entities', []);
        $models = is_array($models) ? array_values(array_filter($models, 'is_string')) : [];

        if ($models === []) {
            $this->warn('Nothing to warm: list the models that use HasBlocks in webx-blocks.entities.');

            return self::SUCCESS;
        }

        $entities = 0;
        $hashes = [];

        foreach ($models as $model) {
            if (! is_subclass_of($model, Model::class) || ! method_exists($model, 'blockTypes')) {
                $this->error("{$model} is not a model using HasBlocks.");

                return self::FAILURE;
            }

            /** @var Model $entity */
            foreach ($model::query()->cursor() as $entity) {
                $entities++;

                /** @var list<string> $types */
                $types = $entity->blockTypes(); // @phpstan-ignore method.notFound (checked above)
                $bundle = $bundles->forSlugs($types);

                if ($bundle instanceof BlockBundle) {
                    $hashes[$bundle->hash] = true;
                }
            }
        }

        $this->info(sprintf('Warmed %d bundle(s) for %d entit%s.', count($hashes), $entities, $entities === 1 ? 'y' : 'ies'));

        return self::SUCCESS;
    }
}
