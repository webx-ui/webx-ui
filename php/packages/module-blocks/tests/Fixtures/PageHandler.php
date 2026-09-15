<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RouteHandler;

/**
 * What a content module registers: one handler for the real address and the preview alike.
 *
 * Publication is decided here, not in the registry and not in the preview route: a draft is a
 * 404 to everybody, and the same draft under a preview token is the page.
 */
final class PageHandler implements RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): BaseResponse
    {
        /** @var Page $entity */
        $grant = PreviewGrant::of($request);

        if (! $entity->isPublished() && $grant === null) {
            throw new NotFoundHttpException;
        }

        $resolution = Resolution::of($request);

        // The content first, the tags after: the bundle is made of what was rendered.
        $content = $entity->renderBlocks();

        $html = sprintf(
            '<html><head><title>%s</title>%s</head><body data-path="%s" data-entity="%s" data-preview="%s">%s</body></html>',
            e((string) $entity->title),
            app(Bundles::class)->tags('styles'),
            e($resolution?->route->path ?? '-'),
            e((string) ($resolution?->entity?->getKey() ?? '-')),
            $grant === null ? 'no' : (string) $grant->adminId,
            $content,
        );

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
