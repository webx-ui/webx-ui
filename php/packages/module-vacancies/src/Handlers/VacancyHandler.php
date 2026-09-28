<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Localization\Locales;
use WebxUi\Routing\RouteHandler;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Rendering\VacancyPage;
use WebxUi\Vacancies\Rendering\Views;

/**
 * What answers once the registry has decided that this address is a vacancy (§4.6).
 *
 * `isVisible()` is the whole of the decision, and the sitemap asks the same thing: a draft is a
 * 404, and the same vacancy under a preview token is shown as it will be — the preview route of
 * `module-blocks` has already laid the draft over the columns by then. A closed vacancy answers
 * like any other (decision 4): links from job boards lead to it, and it says it is closed.
 */
class VacancyHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly VacancyPage $page,
        private readonly Locales $locales,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Vacancy) {
            throw new NotFoundHttpException;
        }

        if (! $entity->isVisible($this->locales->current()) && ! $this->previewing($request)) {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing(['categories']);

        return response($this->views->make('vacancy', $this->page->data($entity))->render());
    }

    private function previewing(Request $request): bool
    {
        return class_exists(PreviewGrant::class) && PreviewGrant::of($request) !== null;
    }
}
