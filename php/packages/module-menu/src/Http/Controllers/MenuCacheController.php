<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Menu\MenuCache;
use WebxUi\Menu\Menus;

/**
 * The reset button, and the one beside the section's heading (§9).
 *
 * It is not "rebuild": nothing is built here. The records are forgotten and the next visitor to
 * a page with this menu on it builds it again — which is why both of these answer 204 and say
 * nothing about what they did.
 *
 * A whole menu at a time, every language of it. An administrator thinking about the header is
 * not thinking about the pair "the header and Ukrainian", and a button that reset one language
 * would be a button somebody presses three times and still disbelieves.
 *
 * Needed not because the automatic reset is unreliable but because the list of places a menu can
 * change from ends where bulk operations begin (§8): a rebuild of the address registry, an
 * import, an UPDATE written by hand. This is what somebody presses to test the guess that they
 * are looking at something stale, without first finding out where artisan lives.
 */
final class MenuCacheController
{
    public function __construct(
        private readonly Menus $menus,
        private readonly MenuCache $cache,
    ) {}

    public function one(string $key): JsonResponse
    {
        if (! in_array($key, $this->menus->keys(), true)) {
            throw new NotFoundHttpException;
        }

        $this->cache->forget($key);

        return ApiResponse::noContent();
    }

    public function all(): JsonResponse
    {
        $this->cache->flush();

        return ApiResponse::noContent();
    }
}
