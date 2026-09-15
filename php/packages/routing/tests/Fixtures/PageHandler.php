<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Routing\RouteHandler;

/**
 * What a content module registers: it gets the entity and the tail, and answers.
 *
 * Publication is decided here rather than in the registry (§8.6), and this fixture is where that
 * is tested: a draft is a 404 to everybody, and the same draft with the preview token is a 200.
 * The registry does not keep a copy of that state and must not grow one — two copies drift.
 */
class PageHandler implements RouteHandler
{
    public const PREVIEW = 'preview';

    public function handle(Request $request, object $entity, string $tail): BaseResponse
    {
        // A type with no such column is simply always published — a category has no drafts.
        $published = ! $entity instanceof Model || (bool) ($entity->getAttribute('published') ?? true);

        if (! $published && $request->query('token') !== self::PREVIEW) {
            throw new NotFoundHttpException;
        }

        return new Response(json_encode([
            'entity' => $entity::class,
            'id' => $entity instanceof Model ? $entity->getKey() : null,
            'tail' => $tail,
            'preview' => ! $published,
        ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
    }
}
