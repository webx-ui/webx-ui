<?php

declare(strict_types=1);

namespace WebxUi\Routing\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Exceptions\RoutingException;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\RouteSync;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * Recompute every address with the formatters as they are configured now.
 *
 * This is the supported way to change the address scheme of a type: point
 * `webx-routing.types.<type>.formatter` at something else and run this. Every address that moves
 * leaves an alias behind, exactly as a rename in the panel does, so no external link dies for it.
 *
 * It is also the repair tool. Addresses drift when rows are written around the observer — an
 * import that did not use `RouteSync::bulk`, a migration that touched slugs with `update()`, a
 * database restored from before a rename — and this is what puts them back in step with the
 * entities.
 */
final class RebuildRoutesCommand extends Command
{
    protected $signature = 'webx:routes:rebuild {--type= : Only this registered type} {--dry-run : Show what would change and write nothing}';

    protected $description = 'Recompute the addresses of every registered type and leave aliases behind';

    public function handle(RouteTypes $types, RouteSync $sync, Container $container): int
    {
        try {
            $chosen = $this->chosen($types);
        } catch (RoutingException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($chosen === []) {
            $this->components->warn('No route types are registered, so there is nothing to rebuild.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $rows = [];
        $failed = 0;

        foreach ($chosen as $type) {
            /** @var Model $model */
            $model = $container->make($type->model);

            $model->newQuery()->chunkById(200, function (EloquentCollection $entities) use ($type, $sync, $dry, &$rows, &$failed): void {
                foreach ($entities as $entity) {
                    $failed += $this->rebuild($entity, $type, $sync, $dry, $rows);
                }
            });
        }

        $this->report($rows, $dry, $failed);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{string, string, string, string}>  $rows
     * @return int How many entities refused to move.
     */
    private function rebuild(Model $entity, RouteType $type, RouteSync $sync, bool $dry, array &$rows): int
    {
        $current = $this->current($entity);

        try {
            // Nothing is written here — a suffix it settles on lands in the entity in memory,
            // and only `sync()` below puts either of them in the database. That is what makes
            // `--dry-run` honest rather than approximate.
            $wanted = $sync->paths($entity, $type);
        } catch (PathRejected $rejected) {
            $rows[] = [$type->type, (string) $entity->getKey(), '—', 'refused: '.$rejected->getMessage()];

            return 1;
        }

        $moving = [];

        foreach ($wanted as $locale => $path) {
            if (($current[$locale] ?? null) !== $path) {
                $moving[$locale] = $path;
            }
        }

        if ($moving === []) {
            return 0;
        }

        foreach ($moving as $locale => $path) {
            $rows[] = [$type->type, (string) $entity->getKey(), $locale, '/'.($current[$locale] ?? '—').'  ->  /'.$path];
        }

        if ($dry) {
            return 0;
        }

        try {
            $sync->sync($entity);
        } catch (PathRejected $rejected) {
            $rows[] = [$type->type, (string) $entity->getKey(), '—', 'refused: '.$rejected->getMessage()];

            return 1;
        }

        return 0;
    }

    /** @return array<string, string> The canonical address of this entity per language. */
    private function current(Model $entity): array
    {
        $paths = [];

        foreach (Route::query()->forEntity($entity)->canonical()->get() as $route) {
            $paths[$route->locale] = $route->path;
        }

        return $paths;
    }

    /**
     * @return list<RouteType>
     */
    private function chosen(RouteTypes $types): array
    {
        $wanted = $this->option('type');

        return is_string($wanted) && $wanted !== ''
            ? [$types->get($wanted)]
            : array_values($types->all());
    }

    /** @param  list<array{string, string, string, string}>  $rows */
    private function report(array $rows, bool $dry, int $failed): void
    {
        if ($rows === []) {
            $this->components->info('Every address already matches its formatter.');

            return;
        }

        $this->table(['Type', 'Id', 'Locale', 'Address'], $rows);

        $moves = count(array_filter($rows, static fn (array $row): bool => $row[2] !== '—'));

        $this->components->info($dry
            ? $moves.' address(es) would move. Nothing was written.'
            : $moves.' address(es) moved; the old ones answer 301.');

        if ($failed > 0) {
            $this->components->error($failed.' entit(ies) could not be moved — see the table.');
        }
    }
}
