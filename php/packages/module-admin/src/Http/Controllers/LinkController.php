<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Localization\Locales;

/**
 * What this panel can be asked to link to (§3 of the menu spec).
 *
 * One controller and four addresses rather than one endpoint per module, for the same reason the
 * notes have one: a link picker is the same picker wherever it is opened, and a copy of it in
 * every section would be a copy of the permission rules to get wrong. What keeps that safe is
 * that a section only appears for an administrator who may open it, and a search for a type that
 * never appeared answers 404 rather than rows.
 */
final class LinkController
{
    /** As many matches as anybody reads before typing another letter. */
    private const LIMIT = 20;

    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly LinkSources $sources,
        private readonly SiteRoutes $routes,
        private readonly Locales $locales,
    ) {}

    /** The sections of the picker, in the order it draws them. */
    public function sources(Request $request): JsonResponse
    {
        $sections = array_map(static fn (LinkSource $source): array => [
            'type' => $source->type(),
            'title' => $source->title(),
            'icon' => $source->icon(),
        ], $this->sources->allowed($request->user()));

        return ApiResponse::data($sections);
    }

    /**
     * Candidates of one type.
     *
     * An empty term is a question too — "show me the first few" — so it is not refused; what the
     * first few are is the source's business.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $source = $this->source((string) $request->query('type'), $request);
        $limit = (int) ($request->query('limit') ?? self::LIMIT);

        $candidates = $source->search(
            trim((string) $request->query('q', '')),
            $this->locale($request),
            $limit,
        );

        return ApiResponse::data(array_map(
            static fn ($candidate): array => $candidate->toArray(),
            $candidates,
        ));
    }

    /**
     * The entities a saved value names — one query per type, not one per link.
     *
     * This is what a form reads when it opens something saved last month: the value holds a morph
     * pair, and what the field has to draw is a title and an address, neither of which is in it.
     * An id that no longer exists comes back missing rather than as an error, because the form
     * has to be able to show the editor which link fell out.
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'links' => ['required', 'array', 'max:200'],
            'links.*.type' => ['required', 'string'],
            'links.*.id' => ['required', 'integer'],
            'locale' => ['nullable', 'string'],
        ]);

        /** @var array<int, array{type: string, id: int}> $links */
        $links = (array) $request->input('links');
        $locale = $this->locale($request);

        $byType = [];

        foreach ($links as $link) {
            $byType[(string) $link['type']][] = (int) $link['id'];
        }

        $resolved = [];

        foreach ($byType as $type => $ids) {
            $source = $this->sources->allowedSource($type, $request->user());

            // A type this reader may not open is left out rather than refused: one unreadable
            // link among twenty must not stop the other nineteen from being drawn.
            if ($source === null) {
                continue;
            }

            foreach ($source->resolve(array_values(array_unique($ids)), $locale) as $id => $candidate) {
                $resolved[] = ['type' => $type, ...$candidate->toArray(), 'id' => $id];
            }
        }

        return ApiResponse::data($resolved);
    }

    /** The named addresses of the site itself, for the field where a path is typed. */
    public function routes(): JsonResponse
    {
        return ApiResponse::data($this->routes->all());
    }

    private function source(string $type, Request $request): LinkSource
    {
        $source = $this->sources->allowedSource($type, $request->user());

        if ($source === null) {
            throw new NotFoundHttpException((string) __('webx-admin::links.no-source'));
        }

        return $source;
    }

    /**
     * The language the candidates are read in: the one asked for, or the one the panel is open
     * in. A code the site does not publish is not an error — the chain behind every translated
     * value answers for it — so it is passed on as it came.
     */
    private function locale(Request $request): string
    {
        $asked = $request->input('locale') ?? $request->query('locale');

        return is_string($asked) && $asked !== '' ? $asked : $this->locales->current();
    }
}
