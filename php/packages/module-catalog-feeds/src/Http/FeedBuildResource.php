<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * The journal of a feed as the panel and an agent read it (§7.6 of the feeds spec).
 */
final class FeedBuildResource
{
    /** What the journal names by itself; the counters say how many there are in all (§12). */
    private const REJECTS = 50;

    private const REISSUES = 20;

    /**
     * @return array<string, mixed>
     */
    public function row(FeedBuild $build, Feed $feed): array
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
    public function rejects(FeedBuild $build, Feed $feed): array
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
    public function reissues(Feed $feed): array
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
