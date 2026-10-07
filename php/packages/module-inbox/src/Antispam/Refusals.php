<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Antispam;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Models\Form;

/**
 * How many submissions the antispam turned away from a form, per day.
 *
 * Refused and trapped submissions are not written anywhere — that is the point of refusing
 * them — so without this the only trace is a log line, and the audit cannot tell a form that
 * robots have found from one they have not. A number per form and day in the cache: no
 * address, no content, nothing personal, and gone on its own after {@see DAYS} days.
 *
 * A cache that is cleared or per-process (`array`) counts nothing, and the audit then reads
 * zero rather than failing — which is what it would read on a site nobody attacks.
 */
final class Refusals
{
    /** Longer than any window the audit is likely to be asked about. */
    public const DAYS = 100;

    public function __construct(private readonly Cache $cache) {}

    public function record(Form $form): void
    {
        $key = $this->key($form, Carbon::now());

        // `add` first: an increment of a key that is not there has no expiry in some stores.
        $this->cache->add($key, 0, Carbon::now()->addDays(self::DAYS));
        $this->cache->increment($key);
    }

    /** Refusals over the last `$days` days, today included. */
    public function since(Form $form, int $days): int
    {
        $total = 0;
        $day = Carbon::now();

        for ($i = 0; $i < max(1, min($days, self::DAYS)); $i++) {
            $total += (int) $this->cache->get($this->key($form, $day->copy()->subDays($i)), 0);
        }

        return $total;
    }

    private function key(Form $form, Carbon $day): string
    {
        return 'webx-inbox:refusals:'.$form->getKey().':'.$day->format('Ymd');
    }
}
