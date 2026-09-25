<?php

declare(strict_types=1);

namespace WebxUi\Events\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Rendering\EventPage;
use WebxUi\Events\Rendering\Views;
use WebxUi\Localization\Locales;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is an event (§4.5).
 *
 * `isVisible()` is the whole of the decision, and the sitemap asks the same thing: a draft is a
 * 404, and the same event under a preview token is shown as it will be — the preview route of
 * `module-blocks` has already laid the draft over the columns by then. A past event answers like
 * any other (decision 6): links lead to it, and it is where the photos of it are.
 *
 * An event in a hidden category answers all the same: it is not the category's property.
 */
class EventHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly EventPage $page,
        private readonly Locales $locales,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Event) {
            throw new NotFoundHttpException;
        }

        if (! $entity->isVisible($this->locales->current()) && ! $this->previewing($request)) {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing(['categories']);

        return response($this->views->make('event', $this->page->data($entity))->render());
    }

    private function previewing(Request $request): bool
    {
        return class_exists(PreviewGrant::class) && PreviewGrant::of($request) !== null;
    }
}
