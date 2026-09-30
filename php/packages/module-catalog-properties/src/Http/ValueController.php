<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\CatalogProperties\Catalog\ValueBook;
use WebxUi\CatalogProperties\Catalog\ValueMerger;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogProperties\Panel\Resources;

/**
 * The reference book of a property (§7.3 of the properties spec): a lazy tree — the children of one
 * node at a time — or, with a search, a flat page of whatever matches at any depth; creating,
 * editing, moving, merging, deleting.
 *
 * «Alphabetical» books answer in the alphabet of the panel's language, «by hand» ones in the
 * tree's order — the order the editor dragged them into.
 */
final class ValueController
{
    public function __construct(
        private readonly ValueMerger $merger,
        private readonly ValueBook $book,
    ) {}

    public function index(Request $request, int $property): JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = $this->book->listing($owner);

        // The values a product holds, by id and at any depth, for the form to name them.
        if (isset($validated['ids'])) {
            return new JsonResponse(['data' => $query->whereKey(array_map('intval', $validated['ids']))->get()->map(static fn (PropertyValue $value): array => [
                ...Resources::value($value),
                // A node of a tree is named by its path — «Metal / Steel» — which a picker opens to.
                'ancestors' => PropertyValue::query()
                    ->where('property_id', $owner->id)
                    ->where('lft', '<', $value->lft)
                    ->where('rgt', '>', $value->rgt)
                    ->orderBy('lft')
                    ->get()
                    ->map(static fn (PropertyValue $ancestor): array => Resources::value($ancestor))
                    ->all(),
            ])->all()]);
        }

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static fn (Builder $any) => $any
                ->whereTranslationLikeAny('title', $like)
                ->orWhere(static fn (Builder $slug) => $slug->whereTranslationLikeAny('slug', $like)));

            $page = $query->paginate((int) ($validated['per_page'] ?? 50));

            return new JsonResponse([
                ...$page->toArray(),
                'data' => array_map(static fn (PropertyValue $value): array => Resources::value($value), $page->items()),
            ]);
        }

        isset($validated['parent_id'])
            ? $query->where('parent_id', (int) $validated['parent_id'])
            : $query->whereNull('parent_id');

        return new JsonResponse(['data' => $query->get()->map(static fn (PropertyValue $value): array => Resources::value($value))->all()]);
    }

    public function store(Request $request, int $property): JsonResponse
    {
        $owner = Property::query()->findOrFail($property);

        return new JsonResponse(['data' => Resources::value($this->book->create($owner, $request->validate(ValueBook::RULES)))], 201);
    }

    public function update(Request $request, int $property, int $value): JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $fields = $request->validate(ValueBook::RULES);
        unset($fields['parent_id']);

        return new JsonResponse(['data' => Resources::value($this->book->update($this->node($owner, $value), $fields))]);
    }

    /**
     * Put a value under `parent_id` (null — the top), before `before_id` (null — last).
     */
    public function move(Request $request, int $property, int $value): JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $validated = $request->validate(['parent_id' => ['nullable', 'integer'], 'before_id' => ['nullable', 'integer']]);

        $moved = $this->book->move(
            $owner,
            $this->node($owner, $value),
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            isset($validated['before_id']) ? (int) $validated['before_id'] : null,
        );

        return new JsonResponse(['data' => Resources::value($moved)]);
    }

    public function merge(Request $request, int $property, int $value): JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $validated = $request->validate(['into' => ['required', 'integer'], 'dry_run' => ['nullable', 'boolean']]);

        $moved = $this->merger->merge($this->node($owner, $value), $this->node($owner, (int) $validated['into']), $request->boolean('dry_run'));

        return new JsonResponse(['data' => ['moved' => $moved]]);
    }

    public function destroy(int $property, int $value): Response|JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $count = $this->book->delete($this->node($owner, $value));

        if ($count > 0) {
            return new JsonResponse([
                'message' => (string) __('webx-catalog-properties::errors.value-in-use', ['count' => $count]),
                'errors' => ['value' => [(string) __('webx-catalog-properties::errors.value-in-use', ['count' => $count])]],
                'meta' => ['products' => $count],
            ], 422);
        }

        return new Response(status: 204);
    }

    private function node(Property $owner, int $id): PropertyValue
    {
        return PropertyValue::query()->where('property_id', $owner->id)->findOrFail($id);
    }
}
