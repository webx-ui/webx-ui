<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\CatalogFeeds\Taxonomy\Taxonomies;
use WebxUi\CatalogFeeds\Taxonomy\TaxonomyFailure;
use WebxUi\CatalogFeeds\Taxonomy\TaxonomyImport;
use WebxUi\Localization\Locales;

/**
 * `catalog/feeds/taxonomies/{key}` (§6.2, §11 of the feeds spec). The search also says how the last
 * import went: an import by address ends in the queue, and this is where the form reads its end.
 */
final class TaxonomyController
{
    public const LOCALE = 'regex:/^[a-zA-Z]{2}([-_][a-zA-Z]{2})?$/';

    public const LIMIT = 20;

    /** Where a list may come from (§13). */
    public const ADDRESS = ['nullable', 'string', 'url', 'starts_with:https://', 'max:500'];

    public function __construct(
        private readonly Taxonomies $taxonomies,
        private readonly Locales $locales,
    ) {}

    public function search(Request $request, string $key): JsonResponse
    {
        $this->known($key);

        $validated = $request->validate([
            'locale' => ['nullable', 'string', self::LOCALE],
            'q' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $locale = (string) ($validated['locale'] ?? $this->locales->defaultCode());

        return new JsonResponse([
            'data' => $this->taxonomies->search($key, $locale, (string) ($validated['q'] ?? ''), (int) ($validated['limit'] ?? self::LIMIT)),
            'taxonomy' => $this->taxonomies->describe($key, $locale),
        ]);
    }

    /** `upload` is read now and answers 200; anything else goes to the queue and answers 202. */
    public function import(Request $request, TaxonomyImport $import, string $key): JsonResponse
    {
        $this->known($key);

        $validated = $request->validate([
            'locale' => ['nullable', 'string', self::LOCALE],
            'url' => [...self::ADDRESS, 'prohibits:upload'],
            'upload' => ['nullable', 'string'],
        ]);

        $locale = (string) ($validated['locale'] ?? $this->locales->defaultCode());
        $upload = $validated['upload'] ?? null;

        if ($upload === null) {
            $import->queue($key, $locale, $validated['url'] ?? null);

            return new JsonResponse(['taxonomy' => $this->taxonomies->describe($key, $locale)], 202);
        }

        try {
            $import->upload($key, $locale, $upload, $request->user());
        } catch (TaxonomyFailure $failure) {
            throw ValidationException::withMessages(['upload' => [$failure->getMessage()]]);
        }

        return new JsonResponse(['taxonomy' => $this->taxonomies->describe($key, $locale)]);
    }

    /** Here and not on the route: who may not ask is told that first, whatever the key. */
    private function known(string $key): void
    {
        abort_unless(in_array($key, Taxonomies::KEYS, true), 404);
    }
}
