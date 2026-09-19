<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blog\Http\Requests\ArticleRequest;
use WebxUi\Blog\Http\Resources\ArticleResource;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Panel\ArticleFilters;
use WebxUi\Blog\Panel\ArticleList;
use WebxUi\Blog\Panel\ArticleWriter;
use WebxUi\Blog\Panel\Revision;

/**
 * The section's list, and one article as a record (§11).
 *
 * A paginator and not a level of a tree, which is the whole difference from `module-pages`: a
 * blog is a hundred articles that all sit at the same depth, so the shape of the screen is a
 * page of twenty with filters over it rather than a branch somebody opens.
 */
final class ArticleController
{
    public function __construct(
        private readonly ArticleList $list,
        private readonly ArticleFilters $filters,
        private readonly ArticleWriter $writer,
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

    public function show(Article $article): JsonResponse
    {
        return ApiResponse::data(new ArticleResource($this->loaded($article)));
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
     * Save.
     *
     * What goes into the draft and what takes effect at once is {@see ArticleWriter}'s to
     * decide. What is left here is the one thing it cannot answer — whether this editor is
     * writing over somebody else.
     */
    public function update(ArticleRequest $request, Article $article): JsonResponse
    {
        $sent = $request->revision();

        if ($sent !== null && $sent !== Revision::of($article)) {
            return $this->conflict($request, $article);
        }

        $this->writer->save(
            $article,
            $request->columns(),
            $request->ids('rubrics'),
            $request->ids('tags'),
            $request->ids('related'),
            $this->author($request),
        );

        return ApiResponse::data(new ArticleResource($this->loaded($article->refresh())));
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
    private function conflict(Request $request, Article $article): JsonResponse
    {
        return new JsonResponse([
            'message' => (string) __('webx-blog::errors.conflict'),
            'data' => (new ArticleResource($this->loaded($article)))->resolve($request),
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
