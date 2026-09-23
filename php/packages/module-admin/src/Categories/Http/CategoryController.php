<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryForm;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;

/**
 * The categories of one module, behind the routes {@see CategoryRoutes} registers for it.
 *
 * Which module is a default of the route (the model's class) rather than a parameter of the
 * address, so the blog's rubrics answer at `blog/rubrics` exactly as they did before this was
 * shared, and a cached route table still knows whose they are.
 *
 * The whole list every time, with no paginator: categories are a menu, a site has eight of them,
 * and a menu you have to page through is a menu that is already wrong.
 */
final class CategoryController
{
    public function __construct(private readonly CategoryForm $form) {}

    public function index(Request $request): JsonResponse
    {
        $model = $this->model($request);
        $query = $this->query($model)->scopes(['withItemCount', 'ordered']);

        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            $query->scopes(['matching' => [$term]]);
        }

        if ($request->boolean('visible')) {
            $query->scopes(['visible']);
        }

        return new JsonResponse([
            'data' => CategoryResource::collection($query->get()),
            'prefix' => $model::categoryKind()->prefix(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $category = $this->form->create($this->model($request), $request->input('title'), $request->input('slug'));

        return ApiResponse::data(new CategoryResource($this->loaded($category)), 201);
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data($this->form->describe($this->loaded($this->find($request))));
    }

    public function update(Request $request): JsonResponse
    {
        $category = $this->find($request);
        $user = $request->user();
        $input = $request->input('values');

        $this->form->save(
            $category,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        return ApiResponse::data($this->form->describe($this->loaded($category)));
    }

    /**
     * Into the bin, if it is empty. A full one refuses with the number of items in it — the
     * number the editor's next move depends on.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->find($request)->delete();

        return ApiResponse::noContent();
    }

    /** Out of the bin, under the address it had, if nobody has taken it since. */
    public function restore(Request $request): JsonResponse
    {
        $category = $this->find($request, trashed: true);

        if (method_exists($category, 'restore')) {
            $category->restore();
        }

        return ApiResponse::data(new CategoryResource($this->loaded($category)));
    }

    /** The order of the menu on the site and of the filters in the panel. */
    public function reorder(Request $request): JsonResponse
    {
        Ordering::move($this->model($request), $this->ids($request));

        return ApiResponse::noContent();
    }

    /**
     * The order of the items: of the whole list, or of one category when it is named — the one
     * an editor drags with that filter on (§2.5 of the services spec).
     */
    public function reorderItems(Request $request): JsonResponse
    {
        $model = (string) $request->route(CategoryRoutes::ITEMS);
        $category = $request->input('category');

        if (! is_subclass_of($model, Model::class)) {
            throw new NotFoundHttpException;
        }

        Ordering::move($model, $this->ids($request), is_numeric($category) ? (int) $category : null);

        return ApiResponse::noContent();
    }

    /**
     * @return class-string<Model&Category>
     */
    private function model(Request $request): string
    {
        $model = (string) $request->route(CategoryRoutes::MODEL);

        if (! is_subclass_of($model, Category::class) || ! is_subclass_of($model, Model::class)) {
            throw new NotFoundHttpException;
        }

        return $model;
    }

    /**
     * @param  class-string<Model&Category>  $model
     * @return Builder<Model&Category>
     */
    private function query(string $model, bool $trashed = false): Builder
    {
        $query = $model::query();

        if ($trashed) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        // The address in one query for the whole list rather than one per row.
        if (method_exists($model, 'routeCanonical')) {
            $query->with('routes');
        }

        return $query;
    }

    /**
     * @return Model&Category
     */
    private function find(Request $request, bool $trashed = false): Model
    {
        $id = $request->route(CategoryRoutes::ID);
        $category = is_numeric($id) ? $this->query($this->model($request), $trashed)->find((int) $id) : null;

        if (! $category instanceof Category) {
            throw new NotFoundHttpException;
        }

        return $category;
    }

    /**
     * @param  Model&Category  $category
     * @return Model&Category
     */
    private function loaded(Model $category): Model
    {
        $category->refresh();
        $category->setAttribute($category::categoryKind()->countKey(), $category->itemCount());

        return $category;
    }

    /**
     * @return list<int>
     */
    private function ids(Request $request): array
    {
        $validated = validator($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])->validate();

        return array_values(array_map(intval(...), (array) $validated['ids']));
    }
}
