<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Delivery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Facades\History;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\CatalogFeeds\Models\Feed;

/**
 * A key that leaked is replaced (§7.5 of the feeds spec). The old one is kept nowhere, so its
 * address answers as any wrong key does; no file has the key in its path, so the new address works
 * at once and nothing is built.
 */
final class FeedKey
{
    public function reissue(Feed $feed): Feed
    {
        return DB::transaction(static function () use ($feed): Feed {
            $locked = Feed::query()->lockForUpdate()->findOrFail($feed->id);

            $locked->access_key = Feed::newKey();
            $locked->key_issued_at = Carbon::now();

            // Quietly: the journal gets the one row below, which says what happened and not how.
            $locked->saveQuietly();

            // Who and through which door are the context's; the value of the key is in no row.
            History::record($locked, HistoryEntry::KEY_REISSUED);

            return $locked;
        });
    }
}
