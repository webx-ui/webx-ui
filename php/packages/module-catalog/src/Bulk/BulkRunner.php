<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\Journal;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\BulkRun;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;

/**
 * Runs a bulk action (§11.4): chooses the products once, then either does them all inside the
 * request — up to `bulk.sync_limit` — or writes a run and hands it to the queue a chunk at a time.
 *
 * A chunk is one transaction and one mark for the engine; a product is a savepoint inside it, so
 * one that refuses is an error beside its name and its neighbours are done anyway. The journal
 * gets one run with a row per product that changed (§11.3), whichever way the work went: the
 * queued chunks write under the run's row as the administrator who started it, with nobody logged
 * in on the worker. A chunk that runs twice — a worker killed after the commit and before the
 * acknowledgement — finds its cursor already past it and does nothing.
 */
final class BulkRunner
{
    public function __construct(
        private readonly BulkActions $actions,
        private readonly BulkSelection $selection,
        private readonly Journal $journal,
        private readonly Catalog $catalog,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Locales $locales,
    ) {}

    /**
     * Check the action, its params and the caller's right to it, and find what it would touch —
     * what `catalog_bulk` with `dry_run` answers: the number and the first twenty.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $selection
     * @param  (callable(string): bool)|null  $can  null — nobody to ask, as on the local stdio server
     * @return array{action: BulkAction, params: array<string, mixed>, ids: list<int>}
     *
     * @throws ValidationException
     */
    public function prepare(string $key, array $params, array $selection, ?callable $can = null): array
    {
        $action = $this->actions->find($key) ?? throw ValidationException::withMessages([
            'action' => [(string) __('webx-catalog::bulk.errors.unknown-action', ['known' => implode(', ', $this->actions->keys())])],
        ]);

        if ($can !== null && ! $can($action->permission())) {
            throw new AccessDeniedHttpException((string) __('webx-catalog::bulk.errors.forbidden', ['permission' => $action->permission()]));
        }

        /** @var array<string, mixed> $checked */
        $checked = Validator::make($params, $action->rules())->validate();

        return [
            'action' => $action,
            'params' => [...$params, ...$checked],
            'ids' => $this->selection->resolve($selection, $action->trashed(), $this->locales->content()),
        ];
    }

    /**
     * Start it: done at once when it is small, queued otherwise. Either way the answer is the
     * run's shape; a done-at-once run has no id to poll.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $selection
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function start(string $key, array $params, array $selection, ?Authenticatable $admin, ?callable $can = null): BulkRun
    {
        ['action' => $action, 'params' => $params, 'ids' => $ids] = $this->prepare($key, $params, $selection, $can);

        $summary = ['action' => $action->key(), 'label' => $action->label(), 'params' => $params, 'total' => count($ids)];

        if (count($ids) <= $this->syncLimit()) {
            return $this->now($action, $params, $ids, $selection, $summary);
        }

        return $this->queue($action, $params, $ids, $selection, $summary, $admin);
    }

    /**
     * One chunk of a queued run, and whether another is left. What the queued job calls.
     */
    public function chunk(int $runId): bool
    {
        $run = BulkRun::query()->find($runId);

        if ($run === null || $run->finished()) {
            return false;
        }

        $action = $this->actions->find($run->action);

        if ($action === null) {
            // A satellite uninstalled while its run waited: nothing can do the rest.
            $this->finish($run, BulkRun::FAILED);

            return false;
        }

        $ids = DB::table('catalog_bulk_run_items')
            ->where('run_id', $run->id)
            ->where('product_id', '>', $run->cursor)
            ->orderBy('product_id')
            ->limit($this->chunkSize())
            ->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            $this->finish($run, BulkRun::DONE);

            return false;
        }

        $admin = $run->admin_id === null ? null : new GenericUser(['id' => $run->admin_id, 'name' => $run->admin_name]);
        $context = $this->context();

        $context->during(HistoryContext::BULK, $admin, null, fn () => $context->inRun($run->history_id, HistoryContext::BULK, function () use ($run, $action, $ids): void {
            DB::transaction(function () use ($run, $action, $ids): void {
                // Read again under the lock: a second worker with the same chunk waits here and
                // then finds the cursor already past it.
                $locked = BulkRun::query()->lockForUpdate()->find($run->id);

                if ($locked === null || $locked->cursor >= $ids[0]) {
                    return;
                }

                $result = $this->process($action, $locked->params ?? [], $ids);

                $locked->forceFill([
                    'status' => BulkRun::RUNNING,
                    'cursor' => $ids[array_key_last($ids)],
                    'done' => $locked->done + $result['done'],
                    'failed' => $locked->failed + $result['failed'],
                    'errors' => array_slice([...($locked->errors ?? []), ...$result['errors']], 0, BulkRun::ERRORS_KEPT),
                ])->save();

                $run->setRawAttributes($locked->getAttributes(), true);
            });
        }));

        if (count($ids) < $this->chunkSize()) {
            $this->finish($run, BulkRun::DONE);

            return false;
        }

        return true;
    }

    /** Mark a run that the queue gave up on, so that the panel stops waiting for it. */
    public function fail(int $runId): void
    {
        $run = BulkRun::query()->find($runId);

        if ($run !== null && ! $run->finished()) {
            $this->finish($run, BulkRun::FAILED);
        }
    }

    public function labelOf(BulkRun $run): string
    {
        return $this->actions->find($run->action)?->label() ?? $run->action;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $summary
     */
    private function now(BulkAction $action, array $params, array $ids, array $selection, array $summary): BulkRun
    {
        $result = $ids === []
            ? ['done' => 0, 'failed' => 0, 'errors' => []]
            : $this->journal->run(Product::TYPE, $summary, fn () => DB::transaction(fn (): array => $this->process($action, $params, $ids)), HistoryContext::BULK);

        return (new BulkRun)->forceFill([
            'action' => $action->key(),
            'params' => $params,
            'selection' => $selection,
            'total' => count($ids),
            'done' => $result['done'],
            'failed' => $result['failed'],
            'errors' => array_slice($result['errors'], 0, BulkRun::ERRORS_KEPT),
            'status' => BulkRun::DONE,
            'created_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $summary
     */
    private function queue(BulkAction $action, array $params, array $ids, array $selection, array $summary, ?Authenticatable $admin): BulkRun
    {
        $context = $this->context();

        // Whoever the door says started it; the caller's own word when the door said nothing.
        [$adminId, $adminName] = $admin === null
            ? [$context->adminId(), $context->adminName()]
            : $context->during($context->source(), $admin, $context->grantId(), static fn (): array => [$context->adminId(), $context->adminName()]);

        $run = DB::transaction(function () use ($action, $params, $ids, $selection, $summary, $adminId, $adminName): BulkRun {
            // Opened now and closed with the last chunk: the run is in the journal from the
            // moment it starts, and every chunk's rows go under it.
            $entry = $this->journal->run(Product::TYPE, $summary, static fn (?HistoryEntry $entry): ?HistoryEntry => $entry, HistoryContext::BULK);

            $run = BulkRun::query()->create([
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'action' => $action->key(),
                'params' => $params,
                'selection' => $selection,
                'total' => count($ids),
                'history_id' => $entry?->id,
                'status' => BulkRun::QUEUED,
            ]);

            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('catalog_bulk_run_items')->insert(array_map(
                    static fn (int $id): array => ['run_id' => $run->id, 'product_id' => $id],
                    $chunk,
                ));
            }

            return $run;
        });

        // After the commit: a worker that picks the job up must find the run and its ids.
        $this->container->make(Dispatcher::class)->dispatch(new ProcessBulkChunk($run->id));

        return $run->refresh();
    }

    /**
     * Do the action to each product, a savepoint apiece, and mark the chunk for the engine once.
     *
     * @param  array<string, mixed>  $params
     * @param  list<int>  $ids
     * @return array{done: int, failed: int, errors: list<array{id: int, name: string, message: string}>}
     */
    private function process(BulkAction $action, array $params, array $ids): array
    {
        $query = $action->trashed() ? Product::onlyTrashed() : Product::query();
        $products = $query->whereKey($ids)->orderBy('id')->get()->keyBy('id');

        $done = 0;
        $errors = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (! $product instanceof Product) {
                // Deleted, restored or gone since the run started.
                $errors[] = ['id' => $id, 'name' => '#'.$id, 'message' => (string) __('webx-catalog::bulk.errors.gone')];

                continue;
            }

            try {
                DB::transaction(static function () use ($action, $product, $params): void {
                    $changes = $action->apply($product, $params);

                    if ($changes !== []) {
                        $product->recordHistory(HistoryEntry::UPDATED, $changes);
                    }
                });

                $done++;
            } catch (ValidationException $refused) {
                $errors[] = ['id' => $id, 'name' => $product->displayName(), 'message' => self::firstMessage($refused)];
            } catch (Throwable $failure) {
                report($failure);
                $errors[] = ['id' => $id, 'name' => $product->displayName(), 'message' => $failure->getMessage()];
            }
        }

        $this->catalog->touch($ids);

        return ['done' => $done, 'failed' => count($errors), 'errors' => $errors];
    }

    private function finish(BulkRun $run, string $status): void
    {
        $run->forceFill(['status' => $status, 'finished_at' => Carbon::now()])->save();

        if ($run->history_id === null) {
            return;
        }

        $entry = HistoryEntry::query()->find($run->history_id);

        if ($entry !== null) {
            $entry->forceFill([
                'summary' => [...($entry->summary ?? []), 'rows' => $entry->rows()->count(), 'done' => $run->done, 'errors' => $run->failed],
            ])->save();
        }
    }

    private static function firstMessage(ValidationException $refused): string
    {
        foreach ($refused->errors() as $messages) {
            foreach ($messages as $message) {
                return (string) $message;
            }
        }

        return $refused->getMessage();
    }

    private function syncLimit(): int
    {
        return max(0, (int) $this->config->get('webx-catalog.bulk.sync_limit', 50));
    }

    private function chunkSize(): int
    {
        return max(1, (int) $this->config->get('webx-catalog.bulk.chunk', 500));
    }

    /** Asked for each time: the context belongs to the request, and this outlives one in a worker. */
    private function context(): HistoryContext
    {
        return $this->container->make(HistoryContext::class);
    }
}
