<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * The journal of a feed (§7.6, §11 of the feeds spec): its builds, what one of them left out, and
 * the key's reissues, which change what a marketplace has to be told as much as a build does.
 */
final class FeedBuildController
{
    /** What the journal names by itself; the counters say how many there are in all (§12). */
    private const REJECTS = 50;

    private const REISSUES = 20;

    public function index(Request $request, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $found->builds()->orderByDesc('id')->paginate((int) ($validated['per_page'] ?? 20));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (FeedBuild $build): array => $this->row($build, $found), $page->items()),
            'reissues' => $this->reissues($found),
        ]);
    }

    public function show(int $feed, int $build): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $row = $found->builds()->findOrFail($build);

        return new JsonResponse(['data' => [...$this->row($row, $found), 'rejects' => $this->rejects($row, $found)]]);
    }

    public function rebuild(FeedBuilder $builder, int $feed): JsonResponse
    {
        $found = Feed::query()->findOrFail($feed);
        $build = $builder->start($found);

        if (! $build instanceof FeedBuild) {
            throw ValidationException::withMessages(['is_active' => [(string) __('webx-catalog-feeds::errors.inactive')]]);
        }

        return new JsonResponse(['data' => $this->row($build, $found)], 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(FeedBuild $build, Feed $feed): array
    {
        return [
            'id' => (int) $build->id,
            'status' => $build->status,
            'trigger' => $build->trigger,
            'queued_at' => $build->queued_at?->toIso8601String(),
            'started_at' => $build->started_at?->toIso8601String(),
            'finished_at' => $build->finished_at?->toIso8601String(),
            'selected' => (int) $build->selected,
            'written' => (int) $build->written,
            'filtered' => (int) $build->filtered,
            'excluded' => (int) $build->excluded,
            'rejected' => (object) ($build->rejected ?? []),
            'warnings' => (object) ($build->warnings ?? []),
            'error' => $build->error,
            'bytes' => $build->bytes === null ? null : (int) $build->bytes,
            'current' => (int) $build->id === (int) $feed->current_build_id,
        ];
    }

    /**
     * @return list<array{product_id: int, name: string, sku: string|null, reason: string}>
     */
    private function rejects(FeedBuild $build, Feed $feed): array
    {
        $rows = DB::table('catalog_feed_build_rejects')->where('build_id', $build->id)->orderBy('product_id')->limit(self::REJECTS)->get();
        $products = Product::withTrashed()->whereKey($rows->pluck('product_id')->all())->get()->keyBy('id');

        return $rows->map(static function (object $row) use ($products, $feed): array {
            $product = $products[(int) $row->product_id] ?? null;

            return [
                'product_id' => (int) $row->product_id,
                'name' => $product instanceof Product ? $product->displayName($feed->locale) : '#'.$row->product_id,
                'sku' => $product?->sku,
                'reason' => (string) $row->reason,
            ];
        })->all();
    }

    /**
     * @return list<array{at: string|null, by: string|null}>
     */
    private function reissues(Feed $feed): array
    {
        return HistoryEntry::query()
            ->where('subject_type', Feed::TYPE)
            ->where('subject_id', $feed->id)
            ->where('event', HistoryEntry::KEY_REISSUED)
            ->orderByDesc('id')
            ->limit(self::REISSUES)
            ->get()
            ->map(static fn (HistoryEntry $entry): array => [
                'at' => $entry->created_at?->toIso8601String(),
                'by' => $entry->admin_name,
            ])
            ->all();
    }
}
