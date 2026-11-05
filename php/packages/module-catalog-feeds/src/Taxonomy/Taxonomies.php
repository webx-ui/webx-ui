<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Taxonomy;

use Illuminate\Support\Collection;
use WebxUi\CatalogFeeds\Models\Taxonomy;
use WebxUi\CatalogFeeds\Models\TaxonomyItem;

/**
 * Which list a language reads, and what is in it (§6.2 of the feeds spec).
 *
 * Google keeps a file for some languages only, and the ids are the same in all of them, so a
 * language without one reads the English list: the feed sends ids, only the words a person sees
 * in the panel are English, and `fallback` says so.
 */
final class Taxonomies
{
    public const GOOGLE = 'google';

    /** What a language without a file of its own reads. */
    public const FALLBACK = 'en-US';

    public const KEYS = [self::GOOGLE];

    private const ADDRESS = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.%s.txt';

    /** Where the region of a language is not the language in capitals. */
    private const REGIONS = [
        'cs' => 'CZ', 'da' => 'DK', 'el' => 'GR', 'en' => 'US', 'ja' => 'JP', 'ko' => 'KR', 'pt' => 'BR',
        'sv' => 'SE', 'uk' => 'UA', 'vi' => 'VN', 'zh' => 'CN',
    ];

    /** The name of the file of a language: `de` is `de-DE`, a region named stays. */
    public function fileLocale(string $locale): string
    {
        [$language, $region] = [...explode('-', str_replace('_', '-', $locale), 2), ''];
        $language = strtolower($language);

        return $language.'-'.strtoupper($region !== '' ? $region : (self::REGIONS[$language] ?? $language));
    }

    public function address(string $fileLocale): string
    {
        return sprintf(self::ADDRESS, $fileLocale);
    }

    /** The list a language reads, null while nothing was imported for it. */
    public function of(string $key, string $locale): ?Taxonomy
    {
        return $this->rows($key, $locale)->first(static fn (Taxonomy $taxonomy): bool => $taxonomy->imported_at !== null);
    }

    /**
     * What the form shows about the list of a language, and how the last import went.
     *
     * @return array<string, mixed>
     */
    public function describe(string $key, string $locale): array
    {
        $file = $this->fileLocale($locale);
        $rows = $this->rows($key, $locale);
        $list = $rows->first(static fn (Taxonomy $taxonomy): bool => $taxonomy->imported_at !== null);
        $last = $rows->first();

        return [
            'key' => $key,
            'locale' => $file,
            'address' => $this->address($file),
            'list_locale' => $list?->locale,
            'fallback' => $list instanceof Taxonomy && $list->locale !== $file,
            'version' => $list?->version,
            'imported_at' => $list?->imported_at?->toIso8601String(),
            'items' => $list instanceof Taxonomy ? $list->items()->count() : 0,
            'status' => $last?->status,
            'error' => $last?->error,
        ];
    }

    /**
     * Every word has to be in the path; an id answers by itself.
     *
     * @return list<array{id: int, external_id: string, path: string}>
     */
    public function search(string $key, string $locale, string $text, int $limit): array
    {
        $list = $this->of($key, $locale);
        $words = preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $list instanceof Taxonomy || $words === []) {
            return [];
        }

        $query = TaxonomyItem::query()->where('taxonomy_id', $list->id);

        if (count($words) === 1 && ctype_digit($words[0])) {
            $query->where('external_id', $words[0]);
        } else {
            foreach ($words as $word) {
                $query->where('path', 'like', '%'.addcslashes($word, '\\%_').'%');
            }
        }

        return $query->orderBy('path')->limit($limit)->get()
            ->map(static fn (TaxonomyItem $item): array => ['id' => (int) $item->id, 'external_id' => $item->external_id, 'path' => $item->path])
            ->all();
    }

    /**
     * The rows a language looks at, its own first.
     *
     * @return Collection<int, Taxonomy>
     */
    private function rows(string $key, string $locale): Collection
    {
        $file = $this->fileLocale($locale);

        return Taxonomy::query()
            ->where('key', $key)
            ->whereIn('locale', [$file, self::FALLBACK])
            ->get()
            ->sortBy(static fn (Taxonomy $taxonomy): int => $taxonomy->locale === $file ? 0 : 1)
            ->values()
            ->toBase();
    }
}
