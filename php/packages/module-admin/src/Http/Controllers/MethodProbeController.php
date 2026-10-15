<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The address `webx:doctor` sends a PUT and a DELETE to, to learn who answers them.
 *
 * Some shared hosts refuse every method but GET and POST in the web server, before PHP: the
 * panel then reads everything and saves nothing. A refusal from the web server and a refusal
 * from Laravel are both a 403 or a 405, so the only way to tell them apart is a mark that only
 * Laravel puts on — this header. The answer says nothing about the site and changes nothing.
 */
final class MethodProbeController
{
    public const HEADER = 'X-Webx-Method-Probe';

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse(['method' => $request->getMethod()], 200, [
            self::HEADER => $request->getMethod(),
            'Cache-Control' => 'no-store',
        ]);
    }
}
