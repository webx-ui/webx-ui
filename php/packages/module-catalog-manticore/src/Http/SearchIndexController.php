<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Http;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use WebxUi\Catalog\Engine\Indexer;
use WebxUi\Catalog\Manticore\IndexStatus;
use WebxUi\Catalog\Manticore\Rebuild\RebuildIndex;
use WebxUi\Catalog\Manticore\Rebuild\RebuildProgress;

/**
 * «System → Search index» (decision 27): what the page shows, and the rebuild as a job on the
 * queue — the request answers at once, and the page follows the progress by asking again.
 */
final class SearchIndexController
{
    public function __construct(private readonly IndexStatus $status) {}

    public function show(): JsonResponse
    {
        abort_unless($this->status->active(), 404);

        return new JsonResponse(['data' => $this->status->report()]);
    }

    /**
     * The rebuild queued, or 409 while one is waiting or running: two would race for one swap.
     * A rebuild unheard of for a quarter of an hour does not count — its worker is gone. One
     * started from the console counts while it holds the shared lock ({@see Indexer::LOCK}).
     */
    public function rebuild(RebuildProgress $progress, Indexer $indexer, Dispatcher $bus): JsonResponse
    {
        abort_unless($this->status->active(), 404);

        if ($progress->busy() || $indexer->rebuilding()) {
            return new JsonResponse([
                'message' => (string) __($progress->busy() ? 'webx-catalog-manticore::panel.rebuild-busy' : 'webx-catalog-manticore::panel.rebuild-locked'),
                'data' => $progress->get(),
            ], 409);
        }

        $progress->queue();
        $bus->dispatch(new RebuildIndex);

        return new JsonResponse(['data' => $progress->get()], 202);
    }
}
