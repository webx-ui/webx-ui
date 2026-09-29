<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Http\Resources\CategoryResource;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Panel\CategoryForm;
use WebxUi\Catalog\Panel\CategoryMover;
use WebxUi\Catalog\Panel\CategoryTree;

/**
 * The categories of the panel (§6, §11.2): the tree with its numbers, one category as its editor
 * opens it, a save, a move, a delete of an empty one and a restore.
 */
final class CategoryController
{
    public function __construct(
        private readonly CategoryForm $form,
        private readonly CategoryTree $tree,
        private readonly CategoryMover $mover,
    ) {}

    /** The whole tree at once: a catalogue has dozens of categories, not thousands. */
    public function index(): JsonResponse
    {
        return ApiResponse::data($this->tree->build());
    }

    public function show(int $category): JsonResponse
    {
        return ApiResponse::data($this->form->describe($this->find($category)));
    }

    /** `{ values, parent_id? }` — at the end of the parent's children, or of the top level. */
    public function store(Request $request): JsonResponse
    {
        $parent = $request->input('parent_id');
        $category = $this->form->create(
            $this->values($request),
            is_numeric($parent) ? (int) $parent : null,
            $this->can($request),
        );

        return ApiResponse::data($this->form->describe($category), 201);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $saved = $this->form->save($this->find($category), $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($saved));
    }

    /**
     * `{ parent_id, before_id }`: under that parent (null — the top level), before that sibling
     * (null — last). The addresses do not move with it: slugs are flat (decision 23). What moves is
     * the trail, and the journal notes the new parent. Answers with the whole tree, which is what
     * the panel redraws.
     */
    public function move(Request $request, int $category): JsonResponse
    {
        $node = $this->find($category);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'before_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($validated['parent_id']) ? $this->find((int) $validated['parent_id']) : null;
        $before = isset($validated['before_id']) ? $this->find((int) $validated['before_id']) : null;

        $this->mover->move($node, $parent, $before);

        return ApiResponse::data($this->tree->build());
    }

    /** Only an empty one (§6.3); a full one is a 422 with the numbers in `meta`. */
    public function destroy(int $category): JsonResponse
    {
        $this->find($category)->delete();

        return ApiResponse::noContent();
    }

    /** Back under its address, if nobody took it meanwhile — otherwise the 422 says who did. */
    public function restore(int $category, Catalog $catalog): JsonResponse
    {
        $found = Category::onlyTrashed()->find($category) ?? throw new NotFoundHttpException;

        DB::transaction(static function () use ($found, $catalog): void {
            $found->restore();
            $catalog->touchCategory($found);
        });

        return ApiResponse::data(new CategoryResource($found->refresh()));
    }

    private function find(int $id): Category
    {
        return Category::query()->find($id) ?? throw new NotFoundHttpException;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
