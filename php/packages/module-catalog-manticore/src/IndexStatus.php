<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Throwable;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Manticore\Rebuild\RebuildProgress;
use WebxUi\Catalog\Models\Product;

/**
 * What «System → Search index» shows and `catalog_index_status` answers (decisions 27–28): the
 * server, each language's table against the products in the database, the queue, and the rebuild
 * started from the panel.
 *
 * Every part is asked on its own, so a server that does not answer still leaves the queue and the
 * database to report — which is exactly when somebody opens this page.
 */
final class IndexStatus
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly Manticore $server,
        private readonly Catalog $catalog,
        private readonly RebuildProgress $progress,
    ) {}

    /** Whether the catalogue runs on Manticore at all — without it the page has nothing to show. */
    public function active(): bool
    {
        return $this->config->get('webx-catalog.engine') === 'manticore';
    }

    /**
     * @return array{
     *     connection: array{address: string, prefix: string|null, version: string|null, available: bool, error: string|null},
     *     products: int,
     *     tables: list<array{locale: string, table: string, state: string, reason: string|null, documents: int|null, rebuilding: bool}>,
     *     queue: array{waiting: int, oldest: string|null},
     *     rebuild: array{state: string, done: int, total: int, queued_at: string|null, started_at: string|null, finished_at: string|null, error: string|null, stalled: bool},
     *     outdated: bool,
     * }
     */
    public function report(): array
    {
        $connection = ['address' => $this->server->address(), 'prefix' => null, 'version' => null, 'available' => false, 'error' => null];
        $tables = [];

        try {
            $connection['prefix'] = $this->server->prefix();
            $connection['version'] = $this->version();
            $connection['available'] = true;

            /** @var ManticoreEngine $engine */
            $engine = $this->container->make(ManticoreEngine::class);

            foreach ($engine->status() as $locale => $table) {
                $tables[] = ['locale' => (string) $locale, ...$table];
            }
        } catch (Throwable $failure) {
            $connection['error'] = $failure->getMessage();
        }

        $queue = $this->catalog->queue();

        return [
            'connection' => $connection,
            // The bin is indexed too: «Deleted» searches with the same engine.
            'products' => Product::withTrashed()->count(),
            'tables' => $tables,
            'queue' => ['waiting' => $queue['waiting'], 'oldest' => $queue['oldest']?->toIso8601String()],
            'rebuild' => $this->progress->get(),
            'outdated' => array_filter($tables, static fn (array $table): bool => $table['state'] !== 'ready') !== [],
        ];
    }

    /**
     * Why one product is or is not found (decision 28): what the database says of it, whether it
     * waits in the queue, and what each language's table holds for it.
     *
     * @return array<string, mixed>
     */
    public function product(int $id): array
    {
        /** @var Product|null $product */
        $product = Product::withTrashed()->find($id);

        if ($product === null) {
            return ['id' => $id, 'exists' => false];
        }

        $queued = DB::table('catalog_index_queue')->where('product_id', $id)->value('queued_at');
        $answer = [
            'id' => $id,
            'exists' => true,
            'deleted' => $product->trashed(),
            'published' => (bool) $product->getAttribute('is_published'),
            'visible' => Product::query()->whereKey($id)->visible()->exists(),
            'queued_since' => is_string($queued) ? $queued : null,
            'tables' => [],
        ];

        try {
            /** @var ManticoreEngine $engine */
            $engine = $this->container->make(ManticoreEngine::class);
            $answer['tables'] = $engine->document($id);
        } catch (Throwable $failure) {
            $answer['error'] = $failure->getMessage();
        }

        return $answer;
    }

    private function version(): ?string
    {
        foreach ($this->server->sql('SHOW VERSION')[0]['data'] ?? [] as $row) {
            if (is_array($row) && ($row['Component'] ?? null) === 'Daemon') {
                return (string) ($row['Version'] ?? '');
            }
        }

        return null;
    }
}
