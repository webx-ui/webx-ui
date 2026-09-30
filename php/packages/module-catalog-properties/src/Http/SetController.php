<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Panel\Resources;

/**
 * The sets of the categories (§3.2, §7.3 of the properties spec): what a category inherits and
 * what it adds, for its «Properties» tab, and the set in force by group, for the product form —
 * which asks again as soon as the main category changes, before anything is saved.
 */
final class SetController
{
    public function __construct(
        private readonly PropertySets $sets,
        private readonly Properties $properties,
    ) {}

    public function show(int $category): JsonResponse
    {
        $node = Category::query()->findOrFail($category);
        $all = $this->properties->all();
        $parent = $node->parent_id === null ? null : (int) $node->parent_id;
        $names = [];
        $inherited = [];

        foreach ($this->sets->rows($parent) as $row) {
            $names[$row['from']] ??= Category::withTrashed()->find($row['from'])?->displayName();
            $inherited[] = [
                'property' => Resources::property($all[$row['property']]),
                'from' => ['id' => $row['from'], 'name' => $names[$row['from']]],
            ];
        }

        $own = [];

        foreach ($this->sets->own($node) as $id) {
            if (isset($all[$id])) {
                $own[] = Resources::property($all[$id]);
            }
        }

        return new JsonResponse(['data' => ['inherited' => $inherited, 'own' => $own]]);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $node = Category::query()->findOrFail($category);
        $validated = $request->validate(['ids' => ['present', 'array'], 'ids.*' => ['integer']]);

        $this->sets->save($node, array_map('intval', array_values($validated['ids'])));

        return $this->show($category);
    }

    /**
     * The set in force, in its order, cut by the groups of the card: groups in their order, the
     * properties without one last.
     */
    public function effective(int $category): JsonResponse
    {
        Category::query()->findOrFail($category);
        $all = $this->properties->all();
        $byGroup = [];

        foreach ($this->sets->effective($category) as $id) {
            $property = $all[$id] ?? null;

            if ($property !== null) {
                $byGroup[$property->group_id ?? 0][] = $property;
            }
        }

        $groups = PropertyGroup::query()->whereKey(array_keys($byGroup))->scopes(['ordered'])->get();
        $answer = [];

        foreach ($groups as $group) {
            $answer[] = [
                'group' => ['id' => $group->id, 'title' => $group->getTranslations('title')],
                'properties' => array_map(static fn (Property $property): array => Resources::property($property), $byGroup[$group->id]),
            ];
            unset($byGroup[$group->id]);
        }

        // Without a group, or of a group in the bin: last, without a heading.
        $rest = array_merge(...array_values($byGroup));

        if ($rest !== []) {
            $answer[] = ['group' => null, 'properties' => array_map(static fn (Property $property): array => Resources::property($property), $rest)];
        }

        return new JsonResponse(['data' => ['groups' => $answer]]);
    }
}
