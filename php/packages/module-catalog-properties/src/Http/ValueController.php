<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Catalog;
use WebxUi\CatalogProperties\Catalog\ProductValues;
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
        private readonly Catalog $catalog,
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

        $query = PropertyValue::query()->where('property_id', $owner->id)->with('image');
        $query->addSelect(['catalog_property_values.*', 'products_count' => DB::table(ProductValues::TABLE)
            ->selectRaw('count(distinct product_id)')
            ->whereColumn('value_id', 'catalog_property_values.id')]);

        $owner->value_order === Property::MANUAL
            ? $query->orderBy('lft')
            : $query->orderByTranslation('title', 'asc', app()->getLocale())->orderBy('id');

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

        if (! $owner->isSelect()) {
            throw ValidationException::withMessages(['property' => [(string) __('webx-catalog-properties::errors.values-select')]]);
        }

        $validated = $this->validated($request);
        $parent = isset($validated['parent_id']) ? $this->node($owner, (int) $validated['parent_id']) : null;

        if ($parent !== null && ! $owner->is_tree) {
            throw ValidationException::withMessages(['parent_id' => [(string) __('webx-catalog-properties::errors.not-tree')]]);
        }

        $value = new PropertyValue(['property_id' => $owner->id]);
        $this->fill($value, $validated);

        DB::transaction(static function () use ($value, $parent): void {
            $parent === null ? $value->saveAsRoot() : $value->appendTo($parent);
        });

        return new JsonResponse(['data' => Resources::value($value->refresh())], 201);
    }

    public function update(Request $request, int $property, int $value): JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $node = $this->node($owner, $value);
        $this->fill($node, $this->validated($request));
        $node->save();

        return new JsonResponse(['data' => Resources::value($node->refresh())]);
    }

    /**
     * Put a value under `parent_id` (null — the top), before `before_id` (null — last).
     */
    public function move(Request $request, int $property, int $value): JsonResponse
    {
        $owner = Property::withTrashed()->findOrFail($property);
        $validated = $request->validate(['parent_id' => ['nullable', 'integer'], 'before_id' => ['nullable', 'integer']]);
        $node = $this->node($owner, $value);
        $parent = isset($validated['parent_id']) ? $this->node($owner, (int) $validated['parent_id']) : null;
        $before = isset($validated['before_id']) ? $this->node($owner, (int) $validated['before_id']) : null;

        if ($parent !== null && ! $owner->is_tree) {
            throw ValidationException::withMessages(['parent_id' => [(string) __('webx-catalog-properties::errors.not-tree')]]);
        }

        if ($parent !== null && $parent->lft >= $node->lft && $parent->rgt <= $node->rgt) {
            throw ValidationException::withMessages(['parent_id' => [(string) __('webx-catalog-properties::errors.move-into-itself')]]);
        }

        DB::transaction(static function () use ($node, $parent, $before): void {
            if ($before !== null && $before->id !== $node->id) {
                $node->insertBefore($before);
            } elseif ($parent !== null) {
                $node->appendTo($parent);
            } else {
                $node->saveAsRoot();
            }
        });

        // A value moved under another is found under it: its products' documents list ancestors.
        $this->catalog->touchQuery($node->affectedProducts());

        return new JsonResponse(['data' => Resources::value($node->refresh())]);
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
        $node = $this->node($owner, $value);
        $count = $node->productCount();

        if ($count > 0) {
            return new JsonResponse([
                'message' => (string) __('webx-catalog-properties::errors.value-in-use', ['count' => $count]),
                'errors' => ['value' => [(string) __('webx-catalog-properties::errors.value-in-use', ['count' => $count])]],
                'meta' => ['products' => $count],
            ], 422);
        }

        $node->delete();

        return new Response(status: 204);
    }

    private function node(Property $owner, int $id): PropertyValue
    {
        return PropertyValue::query()->where('property_id', $owner->id)->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['sometimes', 'nullable'],
            'slug' => ['sometimes', 'nullable'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'image_id' => ['sometimes', 'nullable', 'integer', 'exists:media_files,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fill(PropertyValue $value, array $validated): void
    {
        foreach (['title', 'slug'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            // Languages laid over those there; one sent empty is emptied, which for a slug means
            // «make it again from the name».
            $merged = $value->getTranslations($field);
            $sent = is_array($validated[$field]) ? $validated[$field] : [app()->getLocale() => $validated[$field]];

            foreach ($sent as $locale => $words) {
                if (is_string($words) && trim($words) !== '') {
                    $merged[(string) $locale] = trim($words);
                } else {
                    unset($merged[(string) $locale]);
                }
            }

            $value->setTranslations($field, $merged);
        }

        foreach (['color', 'image_id'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value->setAttribute($field, $validated[$field] === '' ? null : $validated[$field]);
            }
        }
    }
}
