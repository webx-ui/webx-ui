<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Seen;
use WebxUi\Snippets\Panel\SnippetForm;
use WebxUi\Snippets\Panel\SnippetOrder;
use WebxUi\Snippets\Places;

/**
 * The section's API (§7.5): the list by place, one snippet as its editor opens it, a save that
 * is on the site at once, the bin, and the order of a place.
 */
final class SnippetController
{
    public function __construct(
        private readonly SnippetForm $form,
        private readonly Places $places,
        private readonly Seen $seen,
    ) {}

    /**
     * Every place in the order of the config, its snippets in theirs; then the places nobody
     * declares any more that still hold snippets (§4.3) — printed nowhere, so the list says so.
     * With `trashed`, the bin instead: the last thing thrown away first.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('trashed')) {
            return new JsonResponse([
                'data' => Snippet::query()->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')->get()
                    ->map(fn (Snippet $snippet): array => $this->form->row($snippet))
                    ->values()
                    ->all(),
            ]);
        }

        $seen = $this->seen->all();
        $byPlace = Snippet::query()->orderBy('position')->orderBy('id')->get()->groupBy('place');
        $places = [];

        foreach ($this->places->keys() as $key) {
            $places[] = $this->group($key, true, $byPlace->get($key)?->all() ?? [], $seen[$key] ?? null);
        }

        foreach ($byPlace as $key => $snippets) {
            if (! $this->places->has((string) $key)) {
                $places[] = $this->group((string) $key, false, $snippets->all(), null);
            }
        }

        return new JsonResponse([
            'data' => $places,
            'environment' => app()->environment(),
            'everything_printed' => app()->environment(array_values(array_filter((array) config('webx-snippets.environments', ['production']), is_string(...)))),
        ]);
    }

    public function show(Snippet $snippet): JsonResponse
    {
        return ApiResponse::data($this->form->describe($snippet));
    }

    public function store(Request $request): JsonResponse
    {
        $saved = $this->form->save(new Snippet, $this->values($request));

        return ApiResponse::data($this->form->describe($saved['snippet'], $saved['warnings']), 201);
    }

    /** Save — on the site at once; 422 under the name of the field that refused. */
    public function update(Request $request, Snippet $snippet): JsonResponse
    {
        $saved = $this->form->save($snippet, $this->values($request));

        return ApiResponse::data($this->form->describe($saved['snippet'], $saved['warnings']));
    }

    /** Into the bin: off the site, back with everything it had. */
    public function destroy(Snippet $snippet): JsonResponse
    {
        $snippet->delete();

        return ApiResponse::noContent();
    }

    /** Not model-bound: the snippet is in the bin, where route binding cannot see it. */
    public function restore(int $snippet): JsonResponse
    {
        $found = Snippet::query()->onlyTrashed()->findOrFail($snippet);
        $found->restore();

        return ApiResponse::data($this->form->describe($found->refresh()));
    }

    /** Gone for good — only from the bin, so nothing on the site disappears in one click. */
    public function purge(int $snippet): JsonResponse
    {
        Snippet::query()->onlyTrashed()->findOrFail($snippet)->forceDelete();

        return ApiResponse::noContent();
    }

    /** `{ place, ids }` — every snippet of the place in its new order, dragged in ones included. */
    public function reorder(Request $request, SnippetOrder $order): JsonResponse
    {
        $place = $request->input('place');
        $ids = $request->input('ids');

        if (! is_string($place) || ! is_array($ids) || ! array_is_list($ids)) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-snippets::errors.reorder')]);
        }

        $order->reorder($place, array_map(intval(...), array_filter($ids, is_numeric(...))));

        return ApiResponse::noContent();
    }

    /**
     * `last_seen` is the day a page of the site last printed the place (§10.3): a declared place
     * never seen is one the theme may have lost — or one nobody has opened a page with since.
     *
     * @param  list<Snippet>  $snippets
     * @return array<string, mixed>
     */
    private function group(string $key, bool $declared, array $snippets, ?string $lastSeen): array
    {
        return [
            'key' => $key,
            'label' => $this->places->label($key),
            'built_in' => $this->places->builtIn($key),
            'declared' => $declared,
            'last_seen' => $lastSeen,
            'snippets' => array_map(fn (Snippet $snippet): array => $this->form->row($snippet), $snippets),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }
}
