<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * A feed as the panel reads it. The address carries the key, so it goes only to whoever holds
 * `catalog.manage` (§11 of the feeds spec); `catalog.view` sees everything else.
 */
final class FeedResource
{
    public function __construct(private readonly AccessPolicy $access) {}

    /**
     * @return array<string, mixed>
     */
    public function list(Feed $feed, ?Authenticatable $user): array
    {
        $build = $feed->lastBuild;

        return [
            'id' => (int) $feed->id,
            'name' => $feed->name,
            'preset' => $feed->preset,
            'format' => $feed->format,
            'driver' => $feed->driver,
            'locale' => $feed->locale,
            'currency' => $feed->currency,
            'schedule' => $feed->schedule,
            'is_active' => (bool) $feed->is_active,
            'address' => $this->manages($user) ? $feed->address() : null,
            'last_build' => $build instanceof FeedBuild ? [
                'status' => $build->status,
                'written' => (int) $build->written,
                'finished_at' => $build->finished_at?->toIso8601String(),
            ] : null,
            'updated_at' => $feed->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(Feed $feed, ?Authenticatable $user): array
    {
        return [
            ...$this->list($feed, $user),
            'head' => $feed->head,
            'item' => $feed->item,
            'foot' => $feed->foot,
            'separator' => $feed->separator,
            // Objects, empty ones too: a PHP array with no keys would go out as `[]`.
            'filter' => (object) $feed->filter,
            'stock_map' => (object) $feed->stock_map,
            'taxonomy' => $feed->taxonomy,
            'custom_labels' => (object) $feed->custom_labels,
            'sources' => (object) $feed->sources,
            'options' => (object) $feed->options,
            // MariaDB answers with seconds, the form speaks `H:i`.
            'schedule_at' => $feed->schedule_at === null ? null : substr($feed->schedule_at, 0, 5),
            'rebuild_on_change' => (bool) $feed->rebuild_on_change,
            'key_issued_at' => $feed->key_issued_at->toIso8601String(),
        ];
    }

    private function manages(?Authenticatable $user): bool
    {
        return $user instanceof HasPermissions && $this->access->allows($user, 'catalog.manage');
    }
}
