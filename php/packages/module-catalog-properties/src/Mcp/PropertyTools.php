<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\Mcp\CategoryTools;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Catalog\ValueBook;
use WebxUi\CatalogProperties\Catalog\ValueMerger;
use WebxUi\CatalogProperties\Http\IntervalRows;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogProperties\Panel\PropertyForm;
use WebxUi\CatalogProperties\Panel\Resources;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The properties to an agent (§10 of the properties spec), served as the catalogue's own tools —
 * `catalog_properties_*`, `catalog_property_values_*`, `catalog_property_groups_*` and the set of a
 * category as `catalog_categories_properties` — so they carry the catalogue's scopes and
 * permissions (decision 19). The values of a product are not here: `catalog_products_update` writes
 * them through the part of the form, as it writes every satellite's.
 *
 * The doors are the panel's: {@see PropertyForm} for a property, {@see ValueBook} and
 * {@see ValueMerger} for the reference book, {@see IntervalRows} for the intervals,
 * {@see PropertySets} for a set. `dry_run` does the write inside a transaction and takes it back, as
 * the core's tools do, so the answer — a refusal included — is what would really have happened;
 * merging answers it with the number of products, which is what the merger itself counts.
 */
final class PropertyTools
{
    public function __construct(
        private readonly PropertyForm $form,
        private readonly CategoryForm $groups,
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly ValueBook $book,
        private readonly ValueMerger $merger,
        private readonly Locales $locales,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $property = ['type' => ['integer', 'string'], 'description' => 'The property: its id, or its code in any language.'];
        $value = ['type' => ['integer', 'string'], 'description' => 'A value of the property: its id, or its slug in any language.'];
        $words = ['type' => ['string', 'object'], 'description' => 'One language as a string, or every language as { "en": "…" }; a language left out keeps what it had.'];
        $category = ['type' => 'integer', 'description' => 'The category id, as catalog_categories_tree returns it.'];

        return [
            Tool::read(
                'properties_list',
                'The properties of products, in their order: type, code in every language, group, flags, units and how '
                .'many products hold a value. Read it — or catalog://properties — before making one: reuse a property '
                .'rather than a near-duplicate of it.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Only the ones whose name or code contains this.'],
                    'type' => ['type' => 'string', 'enum' => Property::TYPES],
                    'group' => ['type' => 'integer', 'description' => 'Only the ones of this group (catalog_property_groups_list).'],
                    'trashed' => ['type' => 'boolean', 'description' => 'The ones in the bin instead.'],
                ]],
            ),

            Tool::read(
                'properties_get',
                'One property with every value of its editor, its intervals, and the categories that add it to their set.',
                fn (array $arguments): array => $this->describe($this->property($arguments['property'] ?? null, true)),
                ['properties' => ['property' => $property], 'required' => ['property']],
            ),

            Tool::mutating(
                'properties_create',
                'Make a property. The type is chosen once and never changes: select (a reference book of values), '
                .'number, text or bool (yes/no). The flags a type has no use for are put out, not refused — '
                .'catalog://properties says which a type keeps; a text is never a filter. The code is made from the '
                .'name in each language unless given; one taken by another facet is refused with who holds it. '
                .'A new property is in no category\'s set: add it with catalog_categories_properties_set.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $words,
                    'type' => ['type' => 'string', 'enum' => Property::TYPES],
                    'values' => ['type' => 'object', 'description' => 'Any other field of the editor → value: code, group_id, the flags, unit_prefix, unit_suffix, precision, filter_mode, value_order, toggle_slug, seo_pattern.'],
                ], 'required' => ['title', 'type']],
            ),

            Tool::mutating(
                'properties_update',
                'Change a property: the fields of its editor, and for a number the intervals of its filter. Only the fields '
                .'sent change. A code renamed in any language leaves the old address redirecting to the new one. '
                .'Intervals are the whole table at once, in order: a row with an id is that interval, one without is new, '
                .'one left out is gone; each is [min, max), an end left out is open.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'property' => $property,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as properties_get returns them. The type is not one of them.'],
                    'intervals' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => $words,
                        'slug' => $words,
                        'min' => ['type' => ['number', 'null']],
                        'max' => ['type' => ['number', 'null']],
                    ]], 'description' => 'A number\'s intervals, the whole table.'],
                ], 'required' => ['property']],
            ),

            Tool::mutating(
                'properties_delete',
                'Put a property in the bin. The values products hold stay and come back with properties_restore; '
                .'until then its filter is gone and an address with its code leads to the page without that part.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->delete($arguments)),
                ['properties' => ['property' => $property], 'required' => ['property']],
            ),

            Tool::mutating(
                'properties_restore',
                'Take a property out of the bin, with everything the products held of it.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->restore($arguments)),
                ['properties' => ['property' => $property], 'required' => ['property']],
            ),

            Tool::mutating(
                'properties_reorder',
                'Put the properties in a new order — the order of the list, and the order a set shows them in where two '
                .'categories add them. Name them all, first to last.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->reorder($arguments)),
                ['properties' => ['ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'The ids, first to last.']], 'required' => ['ids']],
            ),

            Tool::read(
                'property_values_list',
                'The reference book of a property. Without a search — one level of it: the top, or the children of `parent`. '
                .'With a search — whatever matches at any depth, with the path of each. Every value comes with the '
                .'number of products that hold it. Read it before making a value: reuse rather than duplicate.',
                fn (array $arguments): array => $this->values($arguments),
                ['properties' => [
                    'property' => $property,
                    'parent' => $value,
                    'search' => ['type' => 'string', 'description' => 'Only the values whose name or slug contains this.'],
                ], 'required' => ['property']],
            ),

            Tool::mutating(
                'property_values_create',
                'Add a value to the reference book of a property — at the end, or under `parent` in a tree. The slug is made '
                .'from the name in each language unless given. Colour and picture are shown only where the property has them on.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->createValue($arguments)),
                ['properties' => [
                    'property' => $property,
                    'title' => $words,
                    'slug' => $words,
                    'parent' => $value,
                    'color' => ['type' => 'string', 'description' => '#rrggbb'],
                    'image_id' => ['type' => 'integer', 'description' => 'A picture of the media library.'],
                ], 'required' => ['property', 'title']],
            ),

            Tool::mutating(
                'property_values_update',
                'Change a value: its name, slug, colour or picture. A slug renamed in any language leaves the old address '
                .'redirecting to the new one.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->updateValue($arguments)),
                ['properties' => [
                    'property' => $property,
                    'value' => $value,
                    'values' => ['type' => 'object', 'description' => 'Any of: title, slug, color, image_id.'],
                ], 'required' => ['property', 'value', 'values']],
            ),

            Tool::mutating(
                'property_values_move',
                'Move a value: under `parent` (left out — to the top of a tree), before `before` (left out — last). A book '
                .'that is not a tree only reorders.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->moveValue($arguments)),
                ['properties' => [
                    'property' => $property,
                    'value' => $value,
                    'parent' => $value,
                    'before' => $value,
                ], 'required' => ['property', 'value']],
            ),

            Tool::mutating(
                'property_values_merge',
                'Merge a value into another of the same property — the way to get rid of a duplicate that products hold. '
                .'The products of `value` get `into`, its old addresses lead to `into`, its children in a tree move under '
                .'`into`, and `value` is gone. With dry_run — how many products would move.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->merge($arguments)),
                ['properties' => [
                    'property' => $property,
                    'value' => $value,
                    'into' => $value,
                ], 'required' => ['property', 'value', 'into']],
            ),

            Tool::mutating(
                'property_values_delete',
                'Delete a value no product holds. One that products hold is refused with their number: merge it into '
                .'another instead (property_values_merge).',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->deleteValue($arguments)),
                ['properties' => ['property' => $property, 'value' => $value], 'required' => ['property', 'value']],
            ),

            ...array_map(
                static fn (Tool $tool): Tool => $tool->prefixed('property_groups_'),
                (new CategoryTools(PropertyGroup::class, $this->groups, 'catalog-property-groups'))->all(),
            ),

            Tool::read(
                'categories_properties',
                'The set of properties of a category: what it inherits from the categories above it (with where from), '
                .'what it adds itself, and the set in force, in order. A product has the properties of the set of its '
                .'main category; a value of one outside it is kept but shown nowhere.',
                fn (array $arguments): array => $this->set($this->category($arguments['category'] ?? null)),
                ['properties' => ['category' => $category], 'required' => ['category']],
            ),

            Tool::mutating(
                'categories_properties_set',
                'Write the properties a category adds to its set, in order — the whole list; the inherited ones are not '
                .'named and cannot be taken away. A property a category above already has is refused with its name. '
                .'The products of the whole branch are re-read.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->saveSet($arguments)),
                ['properties' => [
                    'category' => $category,
                    'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'The property ids the category adds, first to last.'],
                ], 'required' => ['category', 'ids']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = ($arguments['trashed'] ?? false) === true ? Property::onlyTrashed() : Property::query();
        $query->with('group')->withProductCount()->orderBy('position')->orderBy('id');

        $term = trim((string) ($arguments['search'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], '', $term).'%';
            $query->where(static fn ($any) => $any
                ->whereTranslationLikeAny('title', $like)
                ->orWhere(static fn ($code) => $code->whereTranslationLikeAny('code', $like)));
        }

        if (is_string($arguments['type'] ?? null)) {
            $query->where('type', $arguments['type']);
        }

        if (isset($arguments['group'])) {
            $query->where('group_id', (int) $arguments['group']);
        }

        $found = $query->get();

        return [
            'count' => $found->count(),
            'properties' => $found->map(static fn (Property $property): array => Resources::property($property))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Property $property): array
    {
        $categories = DB::table(PropertySets::TABLE)->where('property_id', $property->id)->pluck('category_id')->map(static fn (mixed $id): int => (int) $id)->all();

        return [
            'property' => Resources::property($property),
            'values' => $this->form->read($property),
            'intervals' => array_map(static fn (PropertyInterval $interval): array => Resources::interval($interval), $property->intervals()->get()->all()),
            'added_by' => Category::query()->whereKey($categories)->orderBy('lft')->get()
                ->map(static fn (Category $category): array => ['id' => (int) $category->getKey(), 'name' => $category->displayName()])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $values = is_array($arguments['values'] ?? null) ? $arguments['values'] : [];
        $property = $this->form->save(new Property, $this->words([...$values, 'title' => $arguments['title'] ?? null, 'type' => $arguments['type'] ?? null]), $this->can($user));

        return $this->describe($property);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $values = $arguments['values'] ?? null;
        $intervals = $arguments['intervals'] ?? null;

        if (! is_array($values) && ! is_array($intervals)) {
            throw new ToolFailure('Send `values`, `intervals` or both.');
        }

        if (is_array($values) && $values !== []) {
            $property = $this->form->save($property, $this->words($values), $this->can($user));
        }

        if (is_array($intervals)) {
            if (! $property->isNumber()) {
                throw ValidationException::withMessages(['intervals' => [(string) __('webx-catalog-properties::errors.intervals-number')]]);
            }

            $rows = array_values(array_filter($intervals, 'is_array'));
            $errors = IntervalRows::check($rows);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            IntervalRows::save($property, $rows);
        }

        return $this->describe($property->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $count = $property->affectedProducts()->count();
        $property->delete();

        return ['deleted' => $property->id, 'products_keep_values' => $count];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restore(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null, true);

        if (! $property->trashed()) {
            throw new ToolFailure("The property {$property->id} is not in the bin.");
        }

        $property->restore();

        return $this->describe($property->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $ids = $arguments['ids'] ?? null;

        if (! is_array($ids) || $ids === []) {
            throw new ToolFailure('`ids` must be a non-empty list of ids.');
        }

        foreach (array_values($ids) as $position => $id) {
            Property::withTrashed()->whereKey((int) $id)->update(['position' => $position + 1]);
        }

        $this->properties->flush();

        return $this->list([]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function values(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null, true);

        if (! $property->isSelect()) {
            throw new ToolFailure("The property {$property->id} is a {$property->type}: only a select has a reference book.");
        }

        $query = $this->book->listing($property);
        $term = trim((string) ($arguments['search'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], '', $term).'%';
            $found = $query->where(static fn ($any) => $any
                ->whereTranslationLikeAny('title', $like)
                ->orWhere(static fn ($slug) => $slug->whereTranslationLikeAny('slug', $like)))
                ->limit(200)
                ->get();

            return [
                'count' => $found->count(),
                'values' => $found->map(fn (PropertyValue $one): array => [...$this->value($one), 'path' => $this->path($one)])->all(),
            ];
        }

        $parent = isset($arguments['parent']) ? $this->valueOf($property, $arguments['parent']) : null;
        $parent === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parent->id);
        $found = $query->get();

        return [
            'count' => $found->count(),
            'values' => $found->map(fn (PropertyValue $one): array => $this->value($one))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createValue(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $fields = $this->fields($arguments);

        if (isset($arguments['parent'])) {
            $fields['parent_id'] = $this->valueOf($property, $arguments['parent'])->id;
        }

        return ['value' => $this->value($this->book->create($property, $fields))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateValue(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $value = $this->valueOf($property, $arguments['value'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object: title, slug, color, image_id.');
        }

        $fields = $this->fields($values);
        unset($fields['parent_id']);

        return ['value' => $this->value($this->book->update($value, $fields))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function moveValue(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $value = $this->valueOf($property, $arguments['value'] ?? null);
        $parent = isset($arguments['parent']) ? $this->valueOf($property, $arguments['parent'])->id : null;
        $before = isset($arguments['before']) ? $this->valueOf($property, $arguments['before'])->id : null;

        $moved = $this->book->move($property, $value, $parent, $before);

        return ['value' => [...$this->value($moved), 'path' => $this->path($moved)]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function merge(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $from = $this->valueOf($property, $arguments['value'] ?? null);
        $into = $this->valueOf($property, $arguments['into'] ?? null);
        $dryRun = (bool) ($arguments[Tool::DRY_RUN] ?? false);

        $moved = $this->merger->merge($from, $into, $dryRun);

        return $dryRun
            ? ['dry_run' => true, 'would_move' => $moved, 'into' => $this->value($into->refresh())]
            : ['merged' => $from->id, 'moved' => $moved, 'into' => $this->value($into->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteValue(array $arguments): array
    {
        $property = $this->property($arguments['property'] ?? null);
        $value = $this->valueOf($property, $arguments['value'] ?? null);

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            $count = $value->productCount();

            return ['dry_run' => true, 'would_delete' => $count === 0, 'products_count' => $count];
        }

        $count = $this->book->delete($value);

        if ($count > 0) {
            throw new ToolFailure((string) __('webx-catalog-properties::errors.value-in-use', ['count' => $count]).' → catalog_property_values_merge');
        }

        return ['deleted' => $value->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function set(Category $category): array
    {
        $all = $this->properties->all();
        $parent = $category->parent_id === null ? null : (int) $category->parent_id;
        $names = [];
        $inherited = [];

        foreach ($this->sets->rows($parent) as $row) {
            if (isset($all[$row['property']])) {
                $names[$row['from']] ??= Category::withTrashed()->find($row['from'])?->displayName();
                $inherited[] = [...$this->brief($all[$row['property']]), 'from' => ['id' => $row['from'], 'name' => $names[$row['from']]]];
            }
        }

        $own = [];

        foreach ($this->sets->own($category) as $id) {
            if (isset($all[$id])) {
                $own[] = $this->brief($all[$id]);
            }
        }

        return [
            'category' => ['id' => (int) $category->getKey(), 'name' => $category->displayName()],
            'inherited' => $inherited,
            'own' => $own,
            'effective' => $this->sets->effective($category),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveSet(array $arguments): array
    {
        $category = $this->category($arguments['category'] ?? null);
        $ids = $arguments['ids'] ?? null;

        if (! is_array($ids)) {
            throw new ToolFailure('`ids` must be a list of property ids — empty to add none.');
        }

        $this->sets->save($category, array_map('intval', array_values($ids)));

        return $this->set($category);
    }

    /**
     * @return array<string, mixed>
     */
    private function brief(Property $property): array
    {
        return [
            'id' => $property->id,
            'title' => $property->displayName(),
            'code' => $property->getTranslations('code'),
            'type' => $property->type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function value(PropertyValue $value): array
    {
        $row = Resources::value($value);
        unset($row['image']);

        return [...$row, 'image_id' => $value->image_id];
    }

    /**
     * The names above a value, from the top: «Metal / Steel».
     *
     * @return list<string>
     */
    private function path(PropertyValue $value): array
    {
        return PropertyValue::query()
            ->where('property_id', $value->property_id)
            ->where('lft', '<', $value->lft)
            ->where('rgt', '>', $value->rgt)
            ->orderBy('lft')
            ->get()
            ->map(static fn (PropertyValue $ancestor): string => $ancestor->displayName())
            ->all();
    }

    /**
     * The fields of a value the tool was given, checked as the panel's API checks them.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function fields(array $input): array
    {
        $fields = array_intersect_key($input, array_flip(['title', 'slug', 'color', 'image_id']));

        return validator($fields, ValueBook::RULES)->validate();
    }

    /**
     * A translated field sent as one string is that string in the current language: the screen
     * takes a map of languages, an agent often has one.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function words(array $values): array
    {
        foreach ((new Property)->translatable() as $field) {
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales->current() => $values[$field]];
            }
        }

        return $values;
    }

    private function property(mixed $key, bool $trashed = false): Property
    {
        $query = $trashed ? Property::withTrashed() : Property::query();
        $code = is_string($key) ? strtolower(trim($key)) : '';

        $found = match (true) {
            is_int($key), is_string($key) && ctype_digit($key) => $query->find((int) $key),
            preg_match(Property::CODE, $code) === 1 => $query->whereTranslationLikeAny('code', $code)->first(),
            default => null,
        };

        if (! $found instanceof Property) {
            throw new ToolFailure('There is no property '.$this->printable($key).'. catalog_properties_list has them all'.($trashed ? '.' : ', and one in the bin is restored first.'));
        }

        return $found;
    }

    private function valueOf(Property $property, mixed $key): PropertyValue
    {
        $found = is_int($key) || is_string($key) ? $this->book->find($property, $key) : null;

        if ($found === null) {
            throw new ToolFailure('«'.$property->displayName().'» has no value '.$this->printable($key).'. catalog_property_values_list lists them; catalog_property_values_create makes one.');
        }

        return $found;
    }

    private function category(mixed $key): Category
    {
        $found = is_int($key) || (is_string($key) && ctype_digit($key)) ? Category::query()->find((int) $key) : null;

        if (! $found instanceof Category) {
            throw new ToolFailure('There is no category '.$this->printable($key).'. catalog_categories_tree has them all.');
        }

        return $found;
    }

    /**
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions ? static fn (string $permission): bool => $user->hasPermission($permission) : null;
    }

    /**
     * A write, or with `dry_run` the same write taken back. The caches of properties and sets are
     * dropped after a rollback: they would remember what never happened.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function write(array $arguments, Closure $work): array
    {
        if (! (bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return $this->attempt(static fn (): array => DB::transaction($work));
        }

        return $this->attempt(function () use ($work): array {
            DB::beginTransaction();

            try {
                return ['dry_run' => true, 'would_be' => $work()];
            } finally {
                DB::rollBack();
                $this->properties->flush();
                $this->sets->forget();
            }
        });
    }

    /**
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(Closure $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    private function printable(mixed $key): string
    {
        return is_scalar($key) ? '"'.$key.'"' : '(none given)';
    }
}
