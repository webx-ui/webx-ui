<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Blog\Http\Requests\TagMassRequest;
use WebxUi\Blog\Http\Requests\TagMergeRequest;
use WebxUi\Blog\Http\Requests\TagRequest;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Panel\TagList;
use WebxUi\Blog\Support\TagMerge;

/**
 * Tags: made from the article form by the hundred, raked over on a screen of their own (§10).
 *
 * One endpoint serves both, which is why the list is paginated and the dropdown on the article
 * form works anyway: it asks for the first page of thirty, most used first, and that is exactly
 * what a dropdown is worth scrolling. Splitting them would have been two answers to one
 * question — "which tags are there" — kept in step by hand.
 *
 * What is here and nowhere else is the raking: renaming in place, opening a pile of them to the
 * index or shutting it, deleting, and merging. The merge itself lives in {@see TagMerge},
 * because it is a piece of reasoning about pivots and redirects rather than about HTTP.
 */
final class TagController
{
    public function __construct(private readonly TagList $list) {}

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse($this->list->page($request));
    }

    /**
     * A new tag, usually made from the article being written.
     *
     * The flag it starts with is the one in the config: a tag page is out of the index until
     * somebody decides otherwise (§2.9), and that decision belongs on the tags screen rather
     * than in passing while writing a sentence.
     */
    public function store(TagRequest $request): JsonResponse
    {
        $tag = new Tag($request->values());
        $tag->noindex = $request->has('noindex')
            ? $request->boolean('noindex')
            : (bool) config('webx-blog.tags.noindex', true);
        $tag->save();

        return ApiResponse::data($this->list->row($tag), 201);
    }

    /**
     * Renaming one, or changing its mind about the index.
     *
     * A rename is a rename: the address moves only when the address was sent, because a word
     * spelled three ways before lunch would otherwise leave three aliases behind a decision
     * nobody made ({@see TagRequest}).
     */
    public function update(TagRequest $request, Tag $tag): JsonResponse
    {
        $tag->fill($request->values());
        $tag->save();

        return ApiResponse::data($this->list->row($tag->refresh()));
    }

    /**
     * Gone, and gone for good — a tag has no bin.
     *
     * What it was filed under goes with it: the pivot rows are keyed to the tag and the cascade
     * takes them, and so do its rows in the registry, which is why an address that is to survive
     * a merge has to be a redirect rather than an alias (§6).
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $tag->articles()->detach();
        $tag->delete();

        return ApiResponse::noContent();
    }

    /** What the selection bar does to a pile of them at once (§10). */
    public function mass(TagMassRequest $request): JsonResponse
    {
        $tags = Tag::query()->whereIn('id', $request->ids())->get();

        foreach ($tags as $tag) {
            if ($request->action() === TagMassRequest::DELETE) {
                $tag->articles()->detach();
                $tag->delete();

                continue;
            }

            $tag->noindex = $request->action() === TagMassRequest::NOINDEX;
            $tag->save();
        }

        return ApiResponse::data(['affected' => $tags->count()]);
    }

    /**
     * Several tags into one (§6).
     *
     * Irreversible, and the dialog says so before this is reached: the pivot rows that go are
     * gone, and putting a tag back would not put back which articles carried it.
     */
    public function merge(TagMergeRequest $request, TagMerge $merge): JsonResponse
    {
        $keep = Tag::query()->findOrFail($request->keep());

        /** @var list<Tag> $merged */
        $merged = Tag::query()->whereIn('id', $request->merged())->get()->all();

        $carried = $merge->merge($merged, $keep, $request->redirect());

        return ApiResponse::data([
            'tag' => $this->list->row($keep->refresh()),
            // What the toast says: how many articles came out wearing the surviving word.
            'articles_count' => $carried,
            'merged' => count($merged),
        ]);
    }
}
