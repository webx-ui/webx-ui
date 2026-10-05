<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use WebxUi\Routing\RouteHandler;

/** The handler a module registers for its type: it shows the page. */
class MappedPage implements RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): BaseResponse
    {
        return new Response('page');
    }
}
