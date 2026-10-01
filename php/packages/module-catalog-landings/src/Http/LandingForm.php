<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Http;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\CatalogLandings\Catalog\LandingCounter;
use WebxUi\CatalogLandings\Catalog\LandingSet;
use WebxUi\CatalogLandings\Models\Landing;

/**
 * A landing written from the panel (and, later, an agent): the fields checked, the set checked
 * against the registry and the other landings, everything saved in one transaction, the count
 * taken at once (§8.2, §8.4 of the landings spec).
 *
 * The refusals are 422 under the field: a slug taken — by whom, the address registry says — a
 * set another landing of the base holds — which one — the category facet in the set, a facet
 * nobody has. A save clears the mark of attention: the editor has looked (§9).
 */
final class LandingForm
{
    /** The fields a write may carry; a partial update leaves the others as they are. */
    public const FIELDS = [
        'category_id', 'filters', 'name', 'slug', 'h1', 'text_above', 'text_below', 'sort',
        'on_category', 'position', 'is_published',
    ];

    public function __construct(
        private readonly Facets $facets,
        private readonly Sorts $sorts,
        private readonly LandingCounter $counter,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(Landing $landing, array $input): Landing
    {
        $creating = ! $landing->exists;

        Validator::make($input, [
            'category_id' => ['nullable', 'integer'],
            'filters' => [$creating ? 'required' : 'sometimes', 'array'],
            'name' => [$creating ? 'required' : 'sometimes'],
            'slug' => [$creating ? 'required' : 'sometimes'],
            'sort' => ['nullable', 'string', 'max:32'],
            'on_category' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer'],
            'is_published' => ['sometimes', 'boolean'],
            'recommended' => ['sometimes', 'array', 'max:100'],
            'recommended.*' => ['integer'],
            'seo' => ['sometimes', 'nullable', 'array'],
        ])->validate();

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $landing->{$field} = $input[$field];
            }
        }

        $this->check($landing, $input);

        // Saved by an editor: whatever the mark said has been looked at.
        $landing->attention = null;

        DB::transaction(function () use ($landing, $input): void {
            $landing->save();

            if (array_key_exists('recommended', $input)) {
                $this->saveRecommended($landing, array_map('intval', (array) $input['recommended']));
            }

            if (array_key_exists('seo', $input)) {
                $landing->saveSeo(is_array($input['seo']) && $input['seo'] !== [] ? $input['seo'] : null);
            }
        });

        $this->counter->recountOne($landing);

        return $landing->refresh();
    }

    /**
     * The checks a save needs beyond the fields' shapes: the base, the set, the order.
     *
     * @param  array<string, mixed>  $input
     */
    public function check(Landing $landing, array $input = []): void
    {
        $errors = [];

        if ($landing->category_id !== null && ! Category::withTrashed()->whereKey($landing->category_id)->exists()) {
            $errors['category_id'][] = (string) __('validation.exists', ['attribute' => 'category_id']);
        }

        foreach ($this->setErrors(is_array($input['filters'] ?? null) ? $input['filters'] : $landing->filters) as $message) {
            $errors['filters'][] = $message;
        }

        if (! isset($errors['filters'])) {
            $holder = $this->holder($landing->category_id, LandingSet::from($landing->filters), $landing->exists ? (int) $landing->getKey() : null);

            if ($holder !== null) {
                $errors['filters'][] = (string) __('webx-catalog-landings::errors.set-taken', ['name' => $holder->label()]);
            }
        }

        $sort = $landing->defaultSort();

        if ($sort !== null && $sort !== Sorts::DEFAULT && $this->sorts->find($sort) === null) {
            $errors['sort'][] = (string) __('webx-catalog-landings::errors.unknown-sort', ['sort' => $sort]);
        }

        foreach ((array) ($input['recommended'] ?? []) as $id) {
            if (! Product::withTrashed()->whereKey((int) $id)->exists()) {
                $errors['recommended'][] = (string) __('webx-catalog-landings::errors.unknown-product', ['id' => (int) $id]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * What is wrong with a set as it was sent: the category in it, a facet nobody has, nothing at
     * all.
     *
     * @return list<string>
     */
    public function setErrors(mixed $raw): array
    {
        $errors = [];

        foreach (is_array($raw) ? array_keys($raw) : [] as $key) {
            if ($key === CategoryFacet::KEY) {
                $errors[] = (string) __('webx-catalog-landings::errors.category-facet');
            } elseif ($this->facets->find((string) $key) === null) {
                $errors[] = (string) __('webx-catalog-landings::errors.unknown-facet', ['key' => (string) $key]);
            }
        }

        if ($errors === [] && LandingSet::from($raw)->isEmpty()) {
            $errors[] = (string) __('webx-catalog-landings::errors.set-empty');
        }

        return $errors;
    }

    /** The landing that already holds this set on this base, in the bin or not. */
    public function holder(?int $categoryId, LandingSet $set, ?int $except = null): ?Landing
    {
        $hash = $set->hash($categoryId);

        if ($hash === null) {
            return null;
        }

        return Landing::withTrashed()
            ->where('filters_hash', $hash)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except))
            ->first();
    }

    /**
     * @param  list<int>  $ids  in their order
     */
    private function saveRecommended(Landing $landing, array $ids): void
    {
        $rows = [];

        foreach (array_values(array_unique($ids)) as $position => $id) {
            $rows[$id] = ['position' => $position + 1];
        }

        $landing->recommended()->sync($rows);
    }
}
