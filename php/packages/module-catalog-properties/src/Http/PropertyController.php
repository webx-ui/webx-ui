<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Panel\PropertyForm;
use WebxUi\CatalogProperties\Panel\Resources;

/**
 * The properties in the panel (§7.3 of the properties spec): the list, the editor's values, the
 * order, the bin — and the intervals, which are saved all at once and in order, as the table of the
 * form holds them.
 */
final class PropertyController
{
    public function __construct(
        private readonly PropertyForm $form,
        private readonly Properties $properties,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(Property::TYPES)],
            'group' => ['nullable', 'integer'],
            'trashed' => ['nullable', 'boolean'],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = $request->boolean('trashed') ? Property::onlyTrashed() : Property::query();
        $query->with('group')->withProductCount()->orderBy('position')->orderBy('id');

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static fn ($any) => $any
                ->whereTranslationLikeAny('title', $like)
                ->orWhere(static fn ($code) => $code->whereTranslationLikeAny('code', $like)));
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        // The product form names the properties of values it holds outside the set (§7.2).
        if (isset($validated['ids'])) {
            $query->whereKey(array_map('intval', $validated['ids']));
        }

        if (isset($validated['group'])) {
            $query->where('group_id', (int) $validated['group']);
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 50));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(static fn (Property $property): array => Resources::property($property), $page->items()),
        ]);
    }

    public function show(int $property): JsonResponse
    {
        return $this->answer(Property::withTrashed()->findOrFail($property));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['values' => ['required', 'array']]);
        $property = $this->form->save(new Property, (array) $request->input('values'));

        return $this->answer($property, 201);
    }

    public function update(Request $request, int $property): JsonResponse
    {
        $request->validate(['values' => ['required', 'array']]);
        $saved = $this->form->save(Property::query()->findOrFail($property), (array) $request->input('values'));

        return $this->answer($saved);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        DB::transaction(static function () use ($validated): void {
            foreach (array_values($validated['ids']) as $position => $id) {
                Property::withTrashed()->whereKey((int) $id)->update(['position' => $position + 1]);
            }
        });

        $this->properties->flush();

        return new JsonResponse(['data' => ['ids' => array_map('intval', $validated['ids'])]]);
    }

    public function destroy(int $property): Response
    {
        Property::query()->findOrFail($property)->delete();

        return new Response(status: 204);
    }

    public function restore(int $property): JsonResponse
    {
        $trashed = Property::onlyTrashed()->findOrFail($property);
        $trashed->restore();

        return $this->answer($trashed->refresh());
    }

    /**
     * All the intervals of a number at once, in order (§7.3): a row with an id is that interval,
     * one without is new, and one left out is gone. The products of the property are marked — the
     * intervals their numbers fall into are in their documents.
     */
    public function intervals(Request $request, int $property): JsonResponse
    {
        $owner = Property::query()->findOrFail($property);

        $validated = $request->validate([
            'intervals' => ['present', 'array'],
            'intervals.*.id' => ['nullable', 'integer'],
            'intervals.*.title' => ['nullable'],
            'intervals.*.slug' => ['nullable'],
            'intervals.*.min' => ['nullable', 'numeric'],
            'intervals.*.max' => ['nullable', 'numeric'],
        ]);

        if (! $owner->isNumber()) {
            return new JsonResponse(['message' => (string) __('webx-catalog-properties::errors.intervals-number'), 'errors' => ['intervals' => [(string) __('webx-catalog-properties::errors.intervals-number')]]], 422);
        }

        $rows = array_values((array) $validated['intervals']);
        $errors = IntervalRows::check($rows);

        if ($errors !== []) {
            return new JsonResponse(['message' => reset($errors)[0], 'errors' => $errors], 422);
        }

        IntervalRows::save($owner, $rows);

        return new JsonResponse(['data' => array_map(
            static fn (PropertyInterval $interval): array => Resources::interval($interval),
            $owner->intervals()->get()->all(),
        )]);
    }

    private function answer(Property $property, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => [
            'property' => Resources::property($property),
            'values' => $this->form->read($property),
            'intervals' => array_map(static fn (PropertyInterval $interval): array => Resources::interval($interval), $property->intervals()->get()->all()),
        ]], $status);
    }
}
