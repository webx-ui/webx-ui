<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blog\Http\Requests\ArticleRequest;
use WebxUi\Blog\Http\Resources\ArticleResource;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Panel\ArticleFilters;
use WebxUi\Blog\Panel\ArticleForm;
use WebxUi\Blog\Panel\ArticleList;
use WebxUi\Blog\Panel\Revision;

/**
 * The section's list, and one article as its editor opens it (§11).
 *
 * A paginator and not a level of a tree, which is the whole difference from `module-pages`: a
 * blog is a hundred articles that all sit at the same depth, so the shape of the screen is a
 * page of twenty with filters over it rather than a branch somebody opens.
 *
 * The record is thin on purpose. The form is a described screen, so what an article's values
 * are is decided by the description and checked by `ScreenValues` — which is what lets
 * `module-seo` put its card on the editor without this class hearing about it. What is left
 * here is the one thing the screen cannot answer: whether this editor is writing over somebody
 * else.
 */
final class ArticleController
{
    public function __construct(
        private readonly ArticleList $list,
        private readonly ArticleFilters $filters,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->list->build($request)->paginate(
            min(100, max(5, (int) $request->integer('per_page', ArticleList::PER_PAGE))),
        );

        $page->through(static fn (Article $article): array => (new ArticleResource($article))->resolve($request));

        return new JsonResponse([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            // The list says what it can be narrowed by. Beside the rows rather than fetched on
            // its own: the screen cannot draw its filters without them, and three more round
            // trips before the first row appears is three more chances to show half a screen.
            'filters' => $this->filters->all(),
        ]);
    }

    public function show(Request $request, Article $article, ArticleForm $form): JsonResponse
    {
        return ApiResponse::data($form->describe($this->loaded($article), $this->author($request)));
    }

    /**
     * A new article: a title, and the address made out of it.
     *
     * It arrives as a draft — `published_at` is null and nothing is on the site — so the
     * registry gives it an address that answers 404 until somebody publishes. That is the
     * registry's design rather than an oversight: an address held from the start is an address
     * nobody else can take while the article is being written.
     */
    public function store(ArticleRequest $request): JsonResponse
    {
        $article = new Article(['title' => $request->title(), 'slug' => $request->slug()]);
        $article->author_id = $this->author($request);
        $article->save();

        return ApiResponse::data(new ArticleResource($this->loaded($article->refresh())), 201);
    }

    /**
     * Save the draft.
     *
     * A request that names no revision is one that did not read the article first — an import,
     * a script — and is let through: the check protects an editor from a surprise, and there is
     * no editor to surprise.
     */
    public function update(Request $request, Article $article, ArticleForm $form): JsonResponse
    {
        $sent = $request->input('revision');

        if (is_string($sent) && $sent !== Revision::of($article)) {
            return $this->conflict($request, $article, $form);
        }

        $user = $request->user();
        $input = $request->input('values');

        $form->save(
            $article,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            $this->author($request),
        );

        return ApiResponse::data($form->describe($this->loaded($article->refresh()), $this->author($request)));
    }

    /**
     * Into the bin. The address goes with it — an article nobody can reach has no business
     * holding a spelling that the next article called the same thing will want.
     */
    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return ApiResponse::noContent();
    }

    /**
     * Somebody wrote to this article between the editor reading it and saving it.
     *
     * 409 with the article as it now is, so the panel can offer to re-read rather than quietly
     * keeping one of the two edits. The same answer covers two editors and an agent: what is
     * stale is the request, not whoever made it.
     */
    private function conflict(Request $request, Article $article, ArticleForm $form): JsonResponse
    {
        return new JsonResponse([
            'message' => (string) __('webx-blog::errors.conflict'),
            'data' => $form->describe($this->loaded($article), $this->author($request)),
        ], 409);
    }

    private function loaded(Article $article): Article
    {
        return $article->loadMissing(['routes', 'rubrics', 'tags', 'author', 'cover']);
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
