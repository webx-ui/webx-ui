<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Routing\RouteHandler;

/**
 * A content module's handler with a real view: the page's blocks inside a layout whose header and
 * footer are regions. Publication decides as always — and a region's preview carries no page
 * grant, so an unpublished page stays a 404 under it.
 */
final class RegionPageHandler implements RouteHandler
{
    /** The page's own view: its blocks, in the layout that prints the regions. */
    private const PAGE = '<x-region-site::layout><article data-page="{{ $page->title }}">{{ $page->renderBlocks() }}</article></x-region-site::layout>';

    public function handle(Request $request, object $entity, string $tail): BaseResponse
    {
        /** @var RegionPage $entity */
        if (! $entity->isPublished() && PreviewGrant::of($request) === null) {
            throw new NotFoundHttpException;
        }

        return new Response(Blade::render(self::PAGE, ['page' => $entity]), 200, ['Content-Type' => 'text/html']);
    }
}
