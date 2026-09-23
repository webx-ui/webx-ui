<?php

declare(strict_types=1);

namespace WebxUi\Pages;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is a page.
 *
 * One handler for the real address and for the preview alike, and publication is decided here
 * rather than in the registry (§5): a page that was never published is a 404 to everybody, and
 * the same page under a preview token is shown as it will be — the preview route of
 * `module-blocks` has already laid the draft over the columns by the time it gets here.
 */
class PageHandler implements RouteHandler
{
    /** Printed when the site has not written a page view of its own. */
    public const FALLBACK_VIEW = 'webx-pages::show';

    public function __construct(private readonly ViewFactory $views) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Page) {
            throw new NotFoundHttpException;
        }

        if (! $entity->isVisible() && PreviewGrant::of($request) === null) {
            throw new NotFoundHttpException;
        }

        return response($this->views->make($this->view(), ['page' => $entity])->render());
    }

    private function view(): string
    {
        $configured = (string) config('webx-pages.view', 'pages.show');

        return $this->views->exists($configured) ? $configured : self::FALLBACK_VIEW;
    }
}
