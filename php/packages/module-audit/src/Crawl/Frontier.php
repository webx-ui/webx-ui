<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Illuminate\Support\Carbon;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\Mask;

/**
 * The queue of the crawl, kept in `audit_pages` itself: a row without `fetched_at` is an
 * address still to ask. A worker that dies between pieces loses nothing, and "which pages are
 * there" is the same question as "which pages are left".
 *
 * Addresses are added in batches — a page has a hundred links, and a query per link is a hundred
 * thousand queries on a thousand-page site.
 */
final class Frontier
{
    private int $count;

    /** @var list<string> */
    private array $excluded;

    /** @var array<string, true>|null The only addresses a `urls` run may hold. */
    private ?array $only = null;

    public function __construct(private readonly AuditRun $run)
    {
        $this->count = AuditPage::query()->where('run_id', $run->id)->count();
        $this->excluded = $run->excluded();

        if ($run->scope === AuditRun::URLS) {
            $this->only = array_fill_keys($run->urls(), true);
        }
    }

    /** Whether the run may hold this address at all: not an excluded path, on the list if there is one. */
    public function allows(string $url): bool
    {
        if ($this->only !== null) {
            return isset($this->only[$url]);
        }

        return ! Mask::any($this->excluded, $url);
    }

    public function full(): bool
    {
        return $this->count >= $this->limit();
    }

    /**
     * Adds what is not there yet, as far as the limit allows, and lowers the depth of what is
     * there when this way is shorter; marks the sitemap and registry flags either way.
     *
     * @param  list<string>  $urls  Normalised addresses.
     * @param  array{in_sitemap?: bool, in_registry?: bool}  $flags
     * @return array<string, int> Page ids by url hash, of every address that is in the snapshot.
     */
    public function add(array $urls, string $source, ?int $depth, array $flags = []): array
    {
        $byHash = [];

        foreach ($urls as $url) {
            if ($this->allows($url)) {
                $byHash[AuditPage::hash($url)] = $url;
            }
        }

        if ($byHash === []) {
            return [];
        }

        $ids = $this->ids(array_keys($byHash));
        $now = Carbon::now();
        $rows = [];

        foreach ($byHash as $hash => $url) {
            if (isset($ids[$hash]) || $this->count >= $this->limit()) {
                continue;
            }

            $rows[] = [
                'run_id' => $this->run->id,
                'url' => mb_substr($url, 0, 2048),
                'url_hash' => $hash,
                'source' => $source,
                'depth' => $depth,
                'in_sitemap' => (bool) ($flags['in_sitemap'] ?? false),
                'in_registry' => (bool) ($flags['in_registry'] ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->count++;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            AuditPage::query()->insert($chunk);
        }

        $known = array_keys($ids);

        if ($known !== [] && $depth !== null) {
            AuditPage::query()
                ->where('run_id', $this->run->id)
                ->whereIn('url_hash', $known)
                ->where(static fn ($query) => $query->whereNull('depth')->orWhere('depth', '>', $depth))
                ->update(['depth' => $depth]);
        }

        $set = array_filter($flags);

        if ($known !== [] && $set !== []) {
            AuditPage::query()->where('run_id', $this->run->id)->whereIn('url_hash', $known)->update($set);
        }

        return $rows === [] ? $ids : $this->ids(array_keys($byHash));
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, int>
     */
    public function ids(array $hashes): array
    {
        $ids = [];

        foreach (array_chunk($hashes, 500) as $chunk) {
            foreach (AuditPage::query()->where('run_id', $this->run->id)->whereIn('url_hash', $chunk)->pluck('id', 'url_hash') as $hash => $id) {
                $ids[(string) $hash] = (int) $id;
            }
        }

        return $ids;
    }

    private function limit(): int
    {
        return max(1, $this->run->pages_limit);
    }
}
