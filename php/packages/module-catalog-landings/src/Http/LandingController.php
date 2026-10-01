<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogLandings\Catalog\LandingCounter;
use WebxUi\CatalogLandings\Catalog\LandingGenerator;
use WebxUi\CatalogLandings\Catalog\LandingSet;
use WebxUi\CatalogLandings\Catalog\LandingWords;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Models\LandingRun;

/**
 * `/api/cms/catalog/landings` (§8.4 of the landings spec): the list with the panel's filters, a
 * landing, its writes, publication, the bin — and two questions the form asks while it is being
 * filled in: how many products a set shows, and which facets a base offers, with their values.
 */
final class LandingController
{
    public function __construct(
        private readonly LandingForm $form,
        private readonly LandingResource $resource,
        private readonly LandingCounter $counter,
        private readonly LandingWords $words,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:20'],
            'attention' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            'empty' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = $request->boolean('trashed') ? Landing::onlyTrashed() : Landing::query();
        $query->with('category')->orderBy('position')->orderBy('id');

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static fn (Builder $any) => $any
                ->whereTranslationLikeAny('name', $like)
                ->orWhere(static fn (Builder $slug) => $slug->whereTranslationLikeAny('slug', $like)));
        }

        $category = (string) ($validated['category'] ?? '');

        if ($category === 'root') {
            $query->whereNull('category_id');
        } elseif (ctype_digit($category)) {
            // The base as a tree: the category and every one below it.
            $base = Category::withTrashed()->find((int) $category);
            $query->whereIn('category_id', $base instanceof Category ? $base->subtree()->withTrashed()->pluck('id')->all() : [0]);
        }

        if (array_key_exists('attention', $validated) && $validated['attention'] !== null) {
            $request->boolean('attention') ? $query->whereNotNull('attention') : $query->whereNull('attention');
        }

        if (array_key_exists('published', $validated) && $validated['published'] !== null) {
            $query->where('is_published', $request->boolean('published'));
        }

        if (array_key_exists('empty', $validated) && $validated['empty'] !== null) {
            $request->boolean('empty')
                ? $query->where('products_count', 0)
                : $query->where(static fn (Builder $counted) => $counted->whereNull('products_count')->orWhere('products_count', '>', 0));
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 50));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (Landing $landing): array => $this->resource->list($landing), $page->items()),
        ]);
    }

    public function show(int $landing): JsonResponse
    {
        return $this->answer(Landing::withTrashed()->findOrFail($landing));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->answer($this->form->save(new Landing, $request->all()), 201);
    }

    public function update(Request $request, int $landing): JsonResponse
    {
        return $this->answer($this->form->save(Landing::query()->findOrFail($landing), $request->all()));
    }

    public function destroy(int $landing): Response
    {
        Landing::query()->findOrFail($landing)->delete();

        return new Response(status: 204);
    }

    public function restore(int $landing): JsonResponse
    {
        $trashed = Landing::onlyTrashed()->findOrFail($landing);
        $trashed->restore();

        return $this->answer($trashed->refresh());
    }

    /** Published only as a landing a save would accept: a set, not another landing's. */
    public function publish(int $landing): JsonResponse
    {
        $found = Landing::query()->findOrFail($landing);
        $this->form->check($found);

        $found->is_published = true;
        $found->save();

        return $this->answer($found->refresh());
    }

    public function unpublish(int $landing): JsonResponse
    {
        $found = Landing::query()->findOrFail($landing);
        $found->is_published = false;
        $found->save();

        return $this->answer($found->refresh());
    }

    /**
     * How many products a set shows on a base, nothing saved — the landing that already holds it,
     * so the form warns before the save refuses, and the address the set suggests,
     * `{category}-{value}` per language (§8.2).
     */
    public function count(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'filters' => ['present', 'array'],
            'except' => ['nullable', 'integer'],
        ]);

        $errors = $this->form->setErrors($validated['filters']);

        if ($errors !== []) {
            return new JsonResponse(['message' => $errors[0], 'errors' => ['filters' => $errors]], 422);
        }

        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $set = LandingSet::from($validated['filters']);
        $holder = $this->form->holder($categoryId, $set, isset($validated['except']) ? (int) $validated['except'] : null);

        return new JsonResponse(['data' => [
            'count' => $this->counter->count($categoryId, $set->state(app(Facets::class))),
            'taken' => $holder === null ? null : ['id' => (int) $holder->id, 'name' => $holder->label()],
            'suggested' => (object) $this->words->slugs($categoryId === null ? null : Category::withTrashed()->find($categoryId), $set),
        ]]);
    }

    /**
     * The facets a base offers the set (§8.2): the category's own set of facets, or every one on
     * the whole catalogue — the category's facet left out — each with the values its products
     * have, counted, and a range's ends.
     */
    public function facets(Request $request, Catalog $catalog, Facets $facets, CategoryFacets $categoryFacets): JsonResponse
    {
        $validated = $request->validate(['category' => ['nullable', 'integer']]);
        $locale = app()->getLocale();
        $category = isset($validated['category']) ? Category::query()->find((int) $validated['category']) : null;

        if (isset($validated['category']) && ! $category instanceof Category) {
            abort(404);
        }

        $offered = array_values(array_filter(
            $category instanceof Category ? $categoryFacets->visible($category) : $facets->all(),
            static fn (Facet $facet): bool => $facet->key() !== CategoryFacet::KEY,
        ));

        $result = $catalog->engine()->search(new CatalogQuery(
            locale: $locale,
            context: $category instanceof Category ? FilterContext::CATEGORY : FilterContext::ROOT,
            contextId: $category?->id,
            scope: $category instanceof Category ? [CategoryFacet::KEY => FacetValue::of([(string) $category->id])] : [],
            count: array_map(static fn (Facet $facet): string => $facet->key(), $offered),
            perPage: 1,
        ));

        $data = [];

        foreach ($offered as $facet) {
            $counted = $result->facet($facet->key());
            $counts = $counted->counts ?? [];
            $labels = $facet->labels(array_map('strval', array_keys($counts)), $locale);

            $data[] = [
                'key' => $facet->key(),
                'label' => $facet->label(),
                'kind' => $facet->kind()->value,
                'min' => $facet->kind() === FacetKind::Range ? $counted?->min : null,
                'max' => $facet->kind() === FacetKind::Range ? $counted?->max : null,
                'values' => array_values(array_map(
                    static fn (string|int $value, int $count): array => ['value' => (string) $value, 'label' => (string) ($labels[(string) $value] ?? $value), 'count' => $count],
                    array_keys($counts),
                    array_values($counts),
                )),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    /**
     * «Create in bulk» (§8.3): with `dry_run`, the table of what would be made, conflicts marked;
     * without it, the free rows made — at once, or by the queue, whose run the panel then polls.
     */
    public function generate(Request $request, LandingGenerator $generator): JsonResponse
    {
        $params = $generator->validate($request->all());

        if ($request->boolean('dry_run')) {
            $rows = $generator->plan($params);

            return new JsonResponse(['data' => [
                'rows' => $rows,
                'total' => count($rows),
                'free' => count(array_filter($rows, static fn (array $row): bool => $row['conflict'] === null)),
            ]]);
        }

        $run = $generator->run($params, $request->user());

        return new JsonResponse(['data' => $run->toResponse()], $run->exists ? 202 : 200);
    }

    /** How far a queued generation has got. */
    public function run(int $run): JsonResponse
    {
        return new JsonResponse(['data' => LandingRun::query()->findOrFail($run)->toResponse()]);
    }

    private function answer(Landing $landing, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $this->resource->full($landing)], $status);
    }
}
