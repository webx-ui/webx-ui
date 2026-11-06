<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\Catalog\Filter\BaseFacets;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Catalog\CollectionPreview;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Panel\CollectionUsage;
use WebxUi\Localization\Locales;

/**
 * `/api/cms/catalog/collections` (§6.5 of the collections spec): the list with the panel's filters,
 * a collection, its writes, the bin, where it stands, and two questions the form asks while it is
 * being filled in: which facets and presets a base offers, and what the values on the form would show.
 */
final class CollectionController
{
    public function __construct(
        private readonly CollectionForm $form,
        private readonly CollectionResource $resource,
    ) {}

    public function index(Request $request, CollectionUsage $usage): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'attention' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        // The bin is a list of things to bring back, and the last thing thrown away is the one
        // somebody is looking for.
        $query = $request->boolean('trashed')
            ? ProductCollection::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')
            : ProductCollection::query()->orderBy('id');
        $query->withCount('products');

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static fn (Builder $any) => $any->whereTranslationLikeAny('title', $like)->orWhere('key', 'like', $like));
        }

        if (array_key_exists('attention', $validated) && $validated['attention'] !== null) {
            $request->boolean('attention') ? $query->whereNotNull('attention') : $query->whereNull('attention');
        }

        if (array_key_exists('enabled', $validated) && $validated['enabled'] !== null) {
            $query->where('is_enabled', $request->boolean('enabled'));
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 50));
        $places = $usage->counts();

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (ProductCollection $collection): array => [
                ...$this->resource->list($collection),
                // Null without blocks: nothing is counted, which is not the same as none.
                'places' => $places === null ? null : ($places[$collection->key] ?? 0),
            ], $page->items()),
        ]);
    }

    public function show(int $collection): JsonResponse
    {
        return $this->answer(ProductCollection::withTrashed()->findOrFail($collection));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->answer($this->form->save(new ProductCollection, $request->all()), 201);
    }

    public function update(Request $request, int $collection): JsonResponse
    {
        return $this->answer($this->form->save(ProductCollection::query()->findOrFail($collection), $request->all()));
    }

    public function destroy(int $collection): Response
    {
        ProductCollection::query()->findOrFail($collection)->delete();

        return new Response(status: 204);
    }

    public function restore(int $collection): JsonResponse
    {
        $trashed = ProductCollection::onlyTrashed()->findOrFail($collection);
        $trashed->restore();

        return $this->answer($trashed->refresh());
    }

    /**
     * Gone for good, only from the bin, so nothing leaves the site in one click. Whatever still
     * names the key prints nothing, as for a key nobody has (§4.4), so nothing here asks where it stands.
     */
    public function purge(int $collection): Response
    {
        ProductCollection::onlyTrashed()->findOrFail($collection)->forceDelete();

        return new Response(status: 204);
    }

    /** Where it stands (§6.4). A collection in the bin too: that is what restoring brings back. */
    public function usage(int $collection, CollectionUsage $usage): JsonResponse
    {
        return new JsonResponse(['data' => $usage->of(ProductCollection::withTrashed()->findOrFail($collection)->key)]);
    }

    /** The facets a base offers the set, with their values counted, and the presets of the site. */
    public function facets(Request $request, BaseFacets $facets, CollectionPresets $presets): JsonResponse
    {
        $validated = $request->validate(['category' => ['nullable', 'integer']]);
        $locale = app()->getLocale();
        $category = isset($validated['category']) ? Category::query()->find((int) $validated['category']) : null;

        if (isset($validated['category']) && ! $category instanceof Category) {
            abort(404);
        }

        $data = [];

        foreach ($facets->of($category, $locale) as ['facet' => $facet, 'min' => $min, 'max' => $max, 'counts' => $counts]) {
            $labels = $facet->labels(array_map('strval', array_keys($counts)), $locale);

            $data[] = [
                'key' => $facet->key(),
                'label' => $facet->label(),
                'kind' => $facet->kind()->value,
                'min' => $min,
                'max' => $max,
                'values' => array_values(array_map(
                    static fn (string|int $value, int $count): array => ['value' => (string) $value, 'label' => (string) ($labels[(string) $value] ?? $value), 'count' => $count],
                    array_keys($counts),
                    array_values($counts),
                )),
            ];
        }

        return new JsonResponse([
            'data' => $data,
            'presets' => array_map(static fn (CollectionPreset $preset): array => [
                'key' => $preset->key,
                'sort' => $preset->sort,
                'on_sale' => $preset->onSale,
                'facet' => $preset->facet,
            ], $presets->all()),
        ]);
    }

    /**
     * What the values on the form would show, nothing saved (§6.3). The language is the one being
     * edited, or the site's: a product has an address, or has none, in a language.
     */
    public function preview(Request $request, CollectionPreview $preview, Locales $locales): JsonResponse
    {
        $validated = $request->validate(['locale' => ['nullable', 'string', 'max:12']]);
        $locale = (string) ($validated['locale'] ?? $locales->content());
        $picked = array_slice(array_map('intval', (array) $request->input('products', [])), 0, ProductCollection::maxLimit());

        return new JsonResponse(['data' => $preview->of($this->form->draft($request->except('key', 'title', 'link', 'link_text')), $picked, $locale)]);
    }

    private function answer(ProductCollection $collection, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $this->resource->full($collection)], $status);
    }
}
