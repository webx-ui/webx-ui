<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `webx-blocks.editing` off makes the section read-only: every write answers 403 with a
 * sentence, whatever the permission says. On a production site whose types arrive by import
 * this is the whole difference between "can look" and "can deploy".
 */
final class EnsureEditing
{
    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->config->get('webx-blocks.editing', true)) {
            return new JsonResponse(['message' => __('webx-blocks::page.editing-off')], 403);
        }

        return $next($request);
    }
}
