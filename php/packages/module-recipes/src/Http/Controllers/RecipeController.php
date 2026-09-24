<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Http\Requests\RecipeRequest;
use WebxUi\Recipes\Http\Resources\RecipeResource;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Recipes\Panel\RecipeForm;
use WebxUi\Recipes\Panel\RecipeList;
use WebxUi\Recipes\Panel\Revision;

/**
 * The section's list, and one recipe as its editor opens it (§5.10).
 *
 * The form is a described screen, so what a recipe's values are is decided by the description
 * and checked by `ScreenValues`. What is left here is what the screen cannot answer — whether
 * this editor is writing over somebody else — and that a save is one transaction: a refusal half
 * way through leaves nothing behind (the lesson of `services_create`).
 */
final class RecipeController
{
    public function __construct(
        private readonly RecipeList $list,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Every recipe at once, with what the list can be narrowed to beside them: the screen cannot
     * draw its filters without them. `services` is null when no module answers for services —
     * the filter is not drawn then.
     */
    public function index(Request $request, Locales $locales, RelationTargets $targets): JsonResponse
    {
        $locale = $locales->current();
        $service = $targets->find('service');
        $named = static fn (RecipeCategory|RecipeNutrient $category): array => [
            'id' => (int) $category->getKey(),
            'title' => $category->displayName($locale),
        ];

        return new JsonResponse([
            'data' => $this->list->build($request)
                ->get()
                ->map(static fn (Recipe $recipe): array => (new RecipeResource($recipe))->resolve($request))
                ->values()
                ->all(),
            'filters' => [
                'categories' => RecipeCategory::query()->ordered()->get()->map($named)->values()->all(),
                'nutrients' => RecipeNutrient::query()->ordered()->get()->map($named)->values()->all(),
                'services' => $service === null ? null : array_map(
                    static fn (array $row): array => ['id' => $row['id'], 'title' => $row['title']],
                    $service->candidates('', $locale, 1000),
                ),
            ],
        ]);
    }

    public function show(Request $request, Recipe $recipe, RecipeForm $form): JsonResponse
    {
        return ApiResponse::data($form->describe($this->loaded($recipe), $this->author($request)));
    }

    /**
     * A new recipe: a title and the address made of it, as a draft — the registry holds its
     * address from the start, answering 404, so nobody else takes it while it is being written.
     * In a transaction: an address refused leaves no recipe without one.
     */
    public function store(RecipeRequest $request, RecipeForm $form): JsonResponse
    {
        $recipe = $this->db->transaction(static function () use ($request): Recipe {
            $recipe = new Recipe(['title' => $request->title(), 'slug' => $request->slug()]);
            $recipe->save();

            return $recipe;
        });

        return ApiResponse::data($form->describe($this->loaded($recipe->refresh()), $this->author($request)), 201);
    }

    /**
     * Save the draft. A request that names no revision did not read the recipe first — an
     * import, a script — and is let through: there is no editor to surprise.
     */
    public function update(Request $request, Recipe $recipe, RecipeForm $form): JsonResponse
    {
        $sent = $request->input('revision');

        if (is_string($sent) && $sent !== Revision::of($recipe)) {
            return new JsonResponse([
                'message' => (string) __('webx-recipes::errors.conflict'),
                'data' => $form->describe($this->loaded($recipe), $this->author($request)),
            ], 409);
        }

        $user = $request->user();
        $input = $request->input('values');
        $author = $this->author($request);

        $this->db->transaction(static fn () => $form->save(
            $recipe,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            $author,
        ));

        return ApiResponse::data($form->describe($this->loaded($recipe->refresh()), $author));
    }

    /** Into the bin, and the address with it. */
    public function destroy(Recipe $recipe): JsonResponse
    {
        $recipe->delete();

        return ApiResponse::noContent();
    }

    private function loaded(Recipe $recipe): Recipe
    {
        return $recipe->loadMissing(['routes', 'categories', 'nutrients']);
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
