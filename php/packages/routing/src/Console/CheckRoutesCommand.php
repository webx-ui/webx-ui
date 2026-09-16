<?php

declare(strict_types=1);

namespace WebxUi\Routing\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Reserved;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * Is the registry still telling the truth?
 *
 * Everything it looks for is something no constraint can catch, because the other half of each
 * pair lives in another table or in the routing table of the application. A row whose entity was
 * deleted straight from the database, an entity written around the observer, an alias whose
 * canonical row went away, an address a project has since claimed with a route of its own — each
 * one is a page that answers 404, or worse, somebody else's screen.
 *
 * Exit code 1 when anything is found, so a deploy can run it and stop.
 */
final class CheckRoutesCommand extends Command
{
    protected $signature = 'webx:routes:check';

    protected $description = 'Report addresses that no longer match the entities or the application';

    /** @var list<array{string, string}> */
    private array $problems = [];

    public function handle(RouteTypes $types, Locales $locales, Reserved $reserved, Container $container): int
    {
        $registered = $types->all();

        $this->unknownTypes($registered);

        foreach ($registered as $type) {
            $this->orphanedRows($type, $container);
            $this->entitiesWithoutRows($type, $locales, $container);
        }

        $this->brokenAliases();
        $this->shadowedByRoutes($reserved);

        if ($this->problems === []) {
            $this->components->info('Every address has an entity, every entity has an address, and the application answers none of them itself.');

            return self::SUCCESS;
        }

        $this->table(['Problem', 'Where'], $this->problems);
        $this->components->error(count($this->problems).' problem(s) found.');

        return self::FAILURE;
    }

    /** @param  array<string, RouteType>  $registered */
    private function unknownTypes(array $registered): void
    {
        /** @var list<string> $stored */
        $stored = Route::query()->distinct()->pluck('entity_type')->all();

        foreach (array_diff($stored, array_keys($registered)) as $type) {
            $count = Route::query()->where('entity_type', $type)->count();

            // A module that was uninstalled without its addresses being cleared. They cannot
            // answer — nothing knows what to do with them — and they still hold their names
            // against everything that could.
            $this->problems[] = ['no type registered for these rows', sprintf('%s (%d row(s))', $type, $count)];
        }
    }

    /** Rows pointing at an entity that is gone: deleted around the observer, or truncated away. */
    private function orphanedRows(RouteType $type, Container $container): void
    {
        /** @var Model $model */
        $model = $container->make($type->model);

        Route::query()
            ->where('entity_type', $type->type)
            ->orderBy('id')
            ->chunk(500, function (EloquentCollection $rows) use ($model): void {
                /** @var list<int> $ids */
                $ids = $rows->pluck('entity_id')->unique()->all();
                $alive = $model->newQuery()->whereKey($ids)->pluck($model->getKeyName())->all();

                foreach ($rows as $row) {
                    if (! in_array($row->entity_id, $alive, false)) {
                        $this->problems[] = ['the entity is gone', sprintf('/%s -> %s#%d', $row->path, $row->entity_type, $row->entity_id)];
                    }
                }
            });
    }

    /** The other direction: something that should have an address and does not. */
    private function entitiesWithoutRows(RouteType $type, Locales $locales, Container $container): void
    {
        /** @var Model $model */
        $model = $container->make($type->model);

        $codes = $locales->codes() === [] ? [$locales->defaultCode()] : $locales->codes();

        $model->newQuery()->chunkById(500, function (EloquentCollection $entities) use ($type, $codes): void {
            /** @var list<int> $ids */
            $ids = $entities->modelKeys();

            $found = [];

            foreach (Route::query()->where('entity_type', $type->type)->whereIn('entity_id', $ids)->canonical()->get() as $row) {
                $found[$row->entity_id][$row->locale] = true;
            }

            foreach ($entities as $entity) {
                foreach ($codes as $code) {
                    if (isset($found[$entity->getKey()][$code])) {
                        continue;
                    }

                    // A language the entity says it has no address in is not a problem: an
                    // untranslated page is meant to be missing from the registry there (§8),
                    // and reporting it would drown the real findings.
                    if (method_exists($entity, 'hasUrlIn') && ! $entity->hasUrlIn($code)) {
                        continue;
                    }

                    $this->problems[] = ['no address', sprintf('%s#%s in %s', $type->type, (string) $entity->getKey(), $code)];
                }
            }
        });
    }

    /**
     * An alias has to lead somewhere, and it has to lead to a canonical row.
     *
     * The foreign key covers the first half — a canonical row cannot be deleted out from under
     * its aliases — but nothing stops a row that was hand-edited into an alias pointing at
     * another alias, and that is a 301 to a 301.
     */
    private function brokenAliases(): void
    {
        Route::query()->alias()->with('target')->orderBy('id')->chunk(500, function (EloquentCollection $rows): void {
            foreach ($rows as $row) {
                $target = $row->target;

                if (! $target instanceof Route) {
                    $this->problems[] = ['the alias leads nowhere', '/'.$row->path];

                    continue;
                }

                if ($target->isAlias()) {
                    $this->problems[] = ['the alias leads to another alias', sprintf('/%s -> /%s', $row->path, $target->path)];
                }
            }
        });
    }

    /** An address the project has since claimed with a route of its own, so the page never runs. */
    private function shadowedByRoutes(Reserved $reserved): void
    {
        Route::query()->canonical()->orderBy('id')->chunk(500, function (EloquentCollection $rows) use ($reserved): void {
            foreach ($rows as $row) {
                if ($reserved->taken($row->path)) {
                    $this->problems[] = ['the application answers this address itself', '/'.$row->path];
                }
            }
        });
    }
}
