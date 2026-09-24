<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Recipes\Panel\RecipeForm;
use WebxUi\Recipes\Panel\RecipeList;
use WebxUi\Recipes\Panel\Revision;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * What an agent can do with the recipes (§5.11).
 *
 * The same doors the panel uses: the list is {@see RecipeList}, the values go through
 * {@see RecipeForm} — so the screen checks them, a project's field lands in `extra`, and the
 * categories, the nutrients, the services and the similar recipes wait in the draft with the text
 * until somebody publishes. The order is {@see Ordering}, the code behind the drag; a recipe has
 * one order only (decision 4), so there is no category to reorder inside.
 *
 * Creating is the row and its values in one transaction: a value the screen refuses must not
 * leave a bare recipe behind with its address already taken (the lesson of `services_create`).
 *
 * The ingredients and the method are HTML, and the description says so twice: the markup of the
 * page reads `<li>` out of them, and an agent that writes prose gets a recipe Google reads as one
 * ingredient.
 */
final class RecipeTools
{
    /** The fields that are a map of languages. */
    private const TRANSLATED = ['title', 'slug', 'lead', 'ingredients', 'method'];

    /** What the relations are called in the values, and the target each points at. */
    private const RELATIONS = ['services' => 'service', 'related' => Recipe::TYPE];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $recipe = [
            'type' => ['integer', 'string'],
            'description' => 'The recipe: its id, or the address it answers at — "/recipes/oatmeal-with-berries".',
        ];
        $category = [
            'type' => ['integer', 'string'],
            'description' => 'A category: its id or its slug. recipe_categories_list and recipes://catalog have both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string (the default language), or every language as { "en": "…", "ru": "…" }.',
        ];
        $html = 'Write the ingredients and the method as HTML lists — <ul><li>200 g oats</li>…</ul> and '
            .'<ol><li>Boil the milk.</li>…</ol>, one item per ingredient and per step: the page\'s Recipe markup '
            .'reads each <li> as one ingredient and one step, and prose gives it a single long one.';
        $relations = 'categories — ids or slugs, the main one first; nutrients — ids or titles; '
            .'services — the services this recipe leads to, ids or addresses ("/services/nutrition-plan"), '
            .'in the order they should be shown; related — the similar recipes chosen by hand, ids or addresses. '
            .'An empty related list lets the site pick similar recipes itself by shared categories, services and '
            .'nutrients. All four wait in the draft like the text.';

        return [
            Tool::read(
                'list',
                'The recipes in the one order they stand in on the site: what each is called in every language, '
                .'the address it answers at, whether it is on the site, its categories — the first of them the main '
                .'one — and how long it takes. Read this (or recipes://catalog) first: a recipe is named by its '
                .'address, and a second one called the same thing is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Recipes whose title or address contains this, in any language the site has.'],
                    'status' => ['type' => 'string', 'enum' => [
                        Recipe::STATUS_DRAFT,
                        Recipe::STATUS_PUBLISHED,
                        Recipe::STATUS_MODIFIED,
                        Recipe::STATUS_UNPUBLISHED,
                    ], 'description' => 'Never published · on the site · on the site with edits waiting · taken off it.'],
                    'category' => $category + ['description' => 'Only the recipes in this category: its id or its slug.'],
                    'nutrient' => ['type' => ['integer', 'string'], 'description' => 'Only the recipes rich in this: its id or its title (recipe_nutrients_list).'],
                    'service' => ['type' => ['integer', 'string'], 'description' => 'Only the recipes linked to this service: its id or its address.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first. A recipe in the bin has no address.'],
                ]],
                permission: ['recipes.view', 'recipes.manage'],
            ),

            Tool::read(
                'get',
                'One recipe in full: the values of its editor — title, address, lead, ingredients and method in every '
                .'language, the gallery (the first picture is the cover), the nutrition as nutrition.calories … '
                .'nutrition.fiber, minutes and servings, the categories, what it is rich in, the linked services, '
                .'the similar recipes, the SEO card, any field the project added — the revision those values are, '
                .'and a link to the draft as the site would print it.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => ['recipe' => $recipe], 'required' => ['recipe']],
                permission: ['recipes.view', 'recipes.manage'],
            ),

            Tool::mutating(
                'create',
                'Start a recipe at the end of the list. It is a draft: nothing is on the site until somebody '
                .'publishes it. The address is made from the title when you do not write one, and an address a '
                .'category, a page or another recipe already answers at is refused rather than given a suffix — '
                .'recipes and their categories share one level. '.$html,
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text,
                    'slug' => $text + ['description' => 'The last segment of the address, per language; made from the title when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The rest of the editor\'s fields as recipes_get returns them — lead, ingredients, method, gallery, nutrition.*, total_minutes, servings, categories, nutrients, the SEO card, the project\'s fields. '.$relations],
                ], 'required' => ['title']],
                permission: 'recipes.manage',
            ),

            Tool::mutating(
                'update',
                'Change the values of a recipe into its draft. A field left out keeps what it had; a localized '
                .'field sent as { "en": "…" } changes that language only. Send the revision recipes_get gave you and '
                .'the write is refused if somebody saved in between. Everything — the categories and the links '
                .'included — reaches the site when the recipe is published. '.$html,
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'recipe' => $recipe,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as recipes_get returns them. The nutrition may also come as { "nutrition": { "calories": "…" } }. '.$relations],
                    'revision' => ['type' => 'string', 'description' => 'The revision recipes_get returned. Left out, the write goes in over whatever happened since.'],
                ], 'required' => ['recipe', 'values']],
                permission: 'recipes.manage',
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site: its values, categories and links become the recipe and a version is '
                .'written. Ask a person first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => ['recipe' => $recipe], 'required' => ['recipe']],
                permission: 'recipes.manage',
            ),

            Tool::mutating(
                'unpublish',
                'Take a recipe off the site. It answers 404 from then on and leaves the index and its categories; '
                .'its address stays reserved and whatever was being prepared is still there.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['recipe' => $recipe], 'required' => ['recipe']],
                permission: 'recipes.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a recipe in the bin. Its address is released, so afterwards it can only be named by its id. '
                .'Nothing is destroyed: the bin in the panel puts it back, as long as nobody has taken its address '
                .'in the meantime.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['recipe' => $recipe], 'required' => ['recipe']],
                permission: 'recipes.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put recipes in a new order. Recipes have one order — the whole list; a category or a service shows '
                .'its recipes in that same order. Name the recipes in the order they should stand in — the ones you '
                .'leave out keep their places around them.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'recipes' => ['type' => 'array', 'items' => $recipe, 'description' => 'The recipes, first to last.'],
                ], 'required' => ['recipes']],
                permission: 'recipes.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = [
            'q' => (string) ($arguments['search'] ?? ''),
            'status' => (string) ($arguments['status'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $category = $this->category($arguments['category'] ?? null);

        if ($category !== null) {
            $query['category'] = (string) $category->getKey();
        }

        $nutrient = $this->nutrient($arguments['nutrient'] ?? null);

        if ($nutrient !== null) {
            $query['nutrient'] = (string) $nutrient->getKey();
        }

        if (($arguments['service'] ?? null) !== null && $arguments['service'] !== '') {
            $query['service'] = (string) $this->target('services', $arguments['service']);
        }

        $recipes = $this->container->make(RecipeList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'count' => $recipes->count(),
            'recipes' => $recipes->map(fn (Recipe $recipe): array => $this->summary($recipe))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user = null): array
    {
        $recipe = $this->recipe($arguments['recipe'] ?? null);

        return [
            'recipe' => $this->summary($recipe),
            'values' => $this->form()->values($recipe),
            // Send it back with recipes_update, and a write over somebody else's is refused.
            'revision' => Revision::of($recipe),
            'preview_url' => $this->preview($recipe, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $title = $this->text($arguments['title'] ?? null, 'title');
        $slug = isset($arguments['slug']) ? $this->text($arguments['slug'], 'slug') : $this->slugFrom($title);
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => ['title' => $title, 'slug' => $slug, 'fields' => array_keys($values)],
                'would_answer_at' => $this->addresses($slug),
            ];
        }

        $recipe = new Recipe;
        $recipe->setTranslations('title', $title);
        $recipe->setTranslations('slug', $slug);

        // One transaction for the row and its values: the routing observer writes the address on
        // `created`, inside this same transaction, so a refused value takes the address with it.
        $recipe->getConnection()->transaction(function () use ($recipe, $values, $user): void {
            $recipe->save();

            if ($values !== []) {
                $this->form()->save($recipe, $values, $this->can($user), $this->authorId($user));
            }
        });

        return $this->get(['recipe' => $recipe->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $recipe = $this->recipe($arguments['recipe'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. recipes_get says what the fields are.');
        }

        if ($recipe->trashed()) {
            throw new ToolFailure("Recipe #{$recipe->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        $values = $this->prepare($values, $recipe);
        $this->sameRevision($arguments, $recipe);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_write' => 'draft',
                'fields' => array_keys($values),
                'recipe' => $this->reference($recipe),
            ];
        }

        $recipe->getConnection()->transaction(
            fn () => $this->form()->save($recipe, $values, $this->can($user), $this->authorId($user)),
        );

        return $this->get(['recipe' => $recipe->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $recipe = $this->recipe($arguments['recipe'] ?? null);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->reference($recipe),
                'status' => $recipe->status(),
                'has_waiting_edits' => $recipe->hasDraft(),
            ];
        }

        $recipe->publish($this->authorId($user), EntityVersion::SOURCE_MCP);

        return ['recipe' => $this->summary($recipe->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $recipe = $this->recipe($arguments['recipe'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->reference($recipe), 'status' => $recipe->status()];
        }

        $recipe->unpublish();

        return ['recipe' => $this->summary($recipe->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $recipe = $this->recipe($arguments['recipe'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($recipe), 'status' => $recipe->status()];
        }

        $recipe->delete();

        return ['trashed' => true, 'id' => (int) $recipe->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['recipes'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`recipes` is the list of recipes in their new order: ids or addresses.');
        }

        $ids = array_values(array_map(fn (mixed $one): int => (int) $this->recipe($one)->getKey(), $given));

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids];
        }

        Ordering::move(Recipe::class, $ids);

        return $this->list([]);
    }

    /**
     * The values as the screen wants them. A plain string in a translated field is the default
     * language, as in recipes_create — not the language of a request that never chose one. A
     * nested `nutrition` object is unfolded into the five literal names. The services and the
     * similar recipes may be named by address; they reach the screen as ids.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values, ?Recipe $recipe = null): array
    {
        if (array_key_exists('blocks', $values)) {
            throw new ToolFailure('A recipe has no blocks: its page is drawn by the module\'s view. Write its fields instead — recipes_get lists them.');
        }

        if (array_key_exists('nutrition', $values)) {
            $nutrition = $values['nutrition'];
            unset($values['nutrition']);

            if (! is_array($nutrition)) {
                throw new ToolFailure('`nutrition` is an object: { "calories": "…", "protein": "…", "fat": "…", "carbohydrates": "…", "fiber": "…" }.');
            }

            foreach ($nutrition as $key => $words) {
                $values[RecipeForm::NUTRITION.$key] = $words;
            }
        }

        foreach (array_keys($values) as $field) {
            $field = (string) $field;

            if (! str_starts_with($field, RecipeForm::NUTRITION)) {
                continue;
            }

            if (! in_array(substr($field, strlen(RecipeForm::NUTRITION)), Recipe::NUTRITION, true)) {
                throw new ToolFailure(sprintf('`%s` is not a nutrition field. There are five: %s.', $field, implode(', ', Recipe::NUTRITION)));
            }
        }

        $default = $this->locales()->defaultCode();

        foreach ($values as $field => $value) {
            $translated = in_array($field, self::TRANSLATED, true) || str_starts_with((string) $field, RecipeForm::NUTRITION);

            if ($translated && is_string($value)) {
                $values[$field] = [$default => $value];
            }
        }

        // Named as an agent reads them off the catalogue: a category by its slug, a nutrient by its
        // title. The first category stays the main one, so the order is kept.
        foreach (['categories' => $this->category(...), 'nutrients' => $this->nutrient(...)] as $field => $find) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            if (! is_array($values[$field])) {
                throw new ToolFailure("`{$field}` is a list of ids or names, the main one first. An empty list clears it.");
            }

            $values[$field] = array_values(array_map(
                static fn (mixed $one): int => (int) $find($one)?->getKey(),
                $values[$field],
            ));
        }

        foreach (self::RELATIONS as $field => $type) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $given = $values[$field];

            if (! is_array($given)) {
                throw new ToolFailure("`{$field}` is a list of ids or addresses, first to last. An empty list clears it.");
            }

            $ids = array_values(array_map(fn (mixed $one): int => $this->target($field, $one), $given));

            if ($field === 'related' && $recipe !== null && in_array((int) $recipe->getKey(), $ids, true)) {
                throw new ToolFailure('A recipe is not similar to itself: leave its own id out of `related`.');
            }

            $values[$field] = $ids;
        }

        return $values;
    }

    /**
     * One record a relation points at, by id or by the address it answers at — the address is
     * what an agent reads off `services://catalog` and `recipes://catalog`.
     */
    private function target(string $field, mixed $reference): int
    {
        $key = self::RELATIONS[$field] ?? $field;
        $target = $this->targets()->find($key);

        if (! $target instanceof RelationTarget) {
            throw new ToolFailure(
                $key === 'service'
                    ? 'This site has no services module, so a recipe cannot be linked to a service.'
                    : "Nothing on this site answers for [{$key}]."
            );
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $exists = ($target->model)::query()->whereKey((int) $reference)->exists();

            return $exists ? (int) $reference : throw new ToolFailure("No {$key} has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure("A {$key} in `{$field}` is an id, or the address it answers at.");
        }

        $id = $this->answeringAt($reference, new ($target->model));

        return $id ?? throw new ToolFailure("No {$key} answers at [/".UrlNormaliser::key($reference).'].');
    }

    /**
     * A recipe by id or by address.
     */
    private function recipe(mixed $reference): Recipe
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $recipe = Recipe::withTrashed()->find((int) $reference);

            return $recipe instanceof Recipe
                ? $recipe
                : throw new ToolFailure("No recipe has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A recipe is an id, or an address like "/recipes/oatmeal-with-berries".');
        }

        $id = $this->answeringAt($reference, new Recipe);
        $recipe = $id === null ? null : Recipe::query()->find($id);

        return $recipe instanceof Recipe
            ? $recipe
            : throw new ToolFailure(
                'No recipe answers at [/'.UrlNormaliser::key($reference).']. recipes_list has the addresses; a recipe in the bin has none.'
            );
    }

    /** The id of the record of this model the registry has at this address, in any language. */
    private function answeringAt(string $address, Model $model): ?int
    {
        $id = Route::query()
            ->where('path', UrlNormaliser::key($address))
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', $model->getMorphClass())
            ->value('entity_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * A category by id or by slug in any language — the slug is what an agent read off an address.
     */
    private function category(mixed $reference): ?RecipeCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $category = RecipeCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            // The last segment: `recipes/breakfast` and `breakfast` name the same category.
            $slug = Str::afterLast(trim(UrlNormaliser::key($reference), '/'), '/');
            $category = RecipeCategory::query()->whereTranslationLikeAny('slug', $slug)->first();
        } else {
            throw new ToolFailure('`category` is an id or a slug.');
        }

        return $category instanceof RecipeCategory
            ? $category
            : throw new ToolFailure('No such category. recipe_categories_list says what there is.');
    }

    /**
     * What a recipe is rich in, by id or by its title in any language: it has no address.
     */
    private function nutrient(mixed $reference): ?RecipeNutrient
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $nutrient = RecipeNutrient::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            $nutrient = RecipeNutrient::query()->whereTranslationLikeAny('title', trim($reference))->first();
        } else {
            throw new ToolFailure('`nutrient` is an id or a title.');
        }

        return $nutrient instanceof RecipeNutrient
            ? $nutrient
            : throw new ToolFailure('No such nutrient. recipe_nutrients_list says what there is.');
    }

    /**
     * One recipe as an agent needs it — every language at once, the draft's title where there is
     * one, and the registry's addresses, which are what the site answers at right now.
     *
     * @return array<string, mixed>
     */
    private function summary(Recipe $recipe): array
    {
        $recipe->loadMissing(['routes', 'categories']);
        $shown = $recipe->hasDraft() ? $recipe->withDraft() : $recipe;

        $summary = [
            'id' => (int) $recipe->getKey(),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'urls' => $this->urls($recipe),
            'status' => $recipe->status(),
            'has_draft' => $recipe->hasDraft(),
            'position' => (int) $recipe->position,
            'total_minutes' => $shown->total_minutes,
            'updated_at' => $recipe->updated_at?->toAtomString(),
            // The first is the main one: the breadcrumbs go through it.
            'categories' => $recipe->categories
                ->map(static fn (RecipeCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->getTranslations('title'),
                    'slug' => $category->getTranslations('slug'),
                ])
                ->values()
                ->all(),
        ];

        if ($recipe->trashed()) {
            $summary['deleted_at'] = $recipe->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * @return array<string, array{path: string, url: string}>
     */
    private function urls(Recipe $recipe): array
    {
        $urls = [];

        foreach ($recipe->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = ['path' => '/'.$route->path, 'url' => $recipe->url($route->locale)];
            }
        }

        return $urls;
    }

    private function reference(Recipe $recipe): string
    {
        $urls = $this->urls($recipe);
        $address = $urls[$this->locales()->defaultCode()] ?? reset($urls);

        return sprintf('#%s (%s)', $recipe->getKey(), is_array($address) ? $address['path'] : 'no address');
    }

    /**
     * Where a recipe with these slugs would answer, before it exists — what a dry run reports.
     *
     * @param  array<string, string>  $slug
     * @return array<string, string>
     */
    private function addresses(array $slug): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-recipes.prefix', 'recipes'));

        return array_map(
            static fn (string $one): string => '/'.UrlNormaliser::join($prefix, $one),
            $slug,
        );
    }

    /**
     * Run a change, and turn a refusal into something the agent can read: the screen refusing a
     * value and the registry refusing an address are both a `ValidationException`.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (CategoryException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sameRevision(array $arguments, Recipe $recipe): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = Revision::of($recipe);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The recipe changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with recipes_get and redo the edit on what is there now.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts === [] ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * @param  array<string, string>  $title
     * @return array<string, string>
     */
    private function slugFrom(array $title): array
    {
        return array_filter(array_map(static fn (string $text): string => Str::slug($text), $title));
    }

    /** Only with `module-blocks`, which draws previews; a recipe it cannot sign is still worth reading. */
    private function preview(Recipe $recipe, ?Authenticatable $user): ?string
    {
        if (! class_exists(Preview::class)) {
            return null;
        }

        try {
            return Preview::url($recipe, $this->authorId($user));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a card its administrator could not.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function form(): RecipeForm
    {
        return $this->container->make(RecipeForm::class);
    }

    private function targets(): RelationTargets
    {
        return $this->container->make(RelationTargets::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
