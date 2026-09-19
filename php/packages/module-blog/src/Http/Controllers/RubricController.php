<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Blog\Exceptions\BlogException;
use WebxUi\Blog\Http\Requests\RubricRequest;
use WebxUi\Blog\Http\Requests\SortingRequest;
use WebxUi\Blog\Http\Resources\RubricResource;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Panel\Sorting;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Seo\Fields;

/**
 * The sections of the blog (§6, §10, §11).
 *
 * The whole list every time, in the order the panel dragged it into. No paginator and no
 * filters: rubrics are a menu, a site has eight of them, and a menu you have to search is a
 * menu that is already wrong.
 *
 * The one rule with teeth lives on the model rather than here — a rubric that still holds
 * articles refuses to be deleted, and says how many (§6) — so an import and an agent hit the
 * same wall as the button does. What the controller adds is the two things the form edits that
 * are not columns of this table: the cover, which is a library key on one side and a foreign
 * key on the other, and the SEO card, which belongs to `module-seo`.
 */
final class RubricController
{
    public function __construct(private readonly FieldTypes $types) {}

    public function index(): JsonResponse
    {
        $rubrics = Rubric::query()
            ->withCount('articles')
            ->with(['cover', 'routes', 'seo'])
            ->inMenuOrder()
            ->get();

        return new JsonResponse([
            'data' => RubricResource::collection($rubrics),
            // Beside the rows rather than fetched on its own: the form prints the whole address
            // as it is typed, and `remont` says nothing about whether the blog lives at the root
            // of the site or under `/blog/` (§4).
            'prefix' => (string) config('webx-blog.prefix', 'blog'),
        ]);
    }

    public function store(RubricRequest $request): JsonResponse
    {
        $rubric = new Rubric($request->values());
        $rubric->cover_id = $this->coverId($request->coverPath());
        // At the end of the menu, which is the only place a new section can go without moving
        // one somebody else put where it is.
        $rubric->position = (int) Rubric::query()->max('position') + 1;
        $rubric->save();

        $this->saveSeo($rubric, $request);

        return ApiResponse::data(new RubricResource($this->loaded($rubric)), 201);
    }

    public function update(RubricRequest $request, Rubric $rubric): JsonResponse
    {
        $rubric->fill($request->values());

        // Only when the field travelled: a form that saved one part of itself must not empty
        // the picture in another.
        if ($request->has('cover')) {
            $rubric->cover_id = $this->coverId($request->coverPath());
        }

        $rubric->save();

        $this->saveSeo($rubric, $request);

        return ApiResponse::data(new RubricResource($this->loaded($rubric->refresh())));
    }

    /**
     * Into the bin, if it is empty.
     *
     * A full one throws {@see BlogException}, which answers 422 with the
     * number of articles in it — the number the editor's next move depends on (§6).
     */
    public function destroy(Rubric $rubric): JsonResponse
    {
        $rubric->delete();

        return ApiResponse::noContent();
    }

    /** The order of the menu on the site, and of the filters in the panel (§6). */
    public function reorder(SortingRequest $request): JsonResponse
    {
        Sorting::apply(Rubric::query(), $request->ids());

        return ApiResponse::noContent();
    }

    /**
     * The library key the media field holds, as the id of a row.
     *
     * The address is never stored — a private disk hands out links that expire and the image
     * editor writes over the same key (CLAUDE.md §4) — so what travels is the key, and what the
     * column keeps is the file it belongs to.
     */
    private function coverId(?string $path): ?int
    {
        if ($path === null) {
            return null;
        }

        $file = MediaFile::query()->where('path', $path)->first();

        return $file instanceof MediaFile ? (int) $file->getKey() : null;
    }

    /**
     * The card `module-seo` owns, through its own field type.
     *
     * Through the type and not straight into the table: the type is what casts the value, drops
     * what was left blank and decodes the structured data, and a second copy of those rules here
     * would be a second copy that drifts. Only when the card travelled — a save of the rest of
     * the form must not empty a card nobody opened.
     */
    private function saveSeo(Rubric $rubric, RubricRequest $request): void
    {
        if (! $request->has(Fields::SCREEN)) {
            return;
        }

        $value = $request->input(Fields::SCREEN);
        $type = $this->types->get(Fields::SCREEN);

        if ($type === null) {
            return;
        }

        $stored = is_array($value) ? $type->store($value, []) : null;

        $rubric->saveSeo(is_array($stored) && $stored !== [] ? $stored : null);
    }

    private function loaded(Rubric $rubric): Rubric
    {
        return $rubric->loadCount('articles')->load(['cover', 'routes', 'seo']);
    }
}
