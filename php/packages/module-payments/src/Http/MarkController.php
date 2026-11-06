<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\PaymentMarks;

/**
 * `{script_path}/marks/{key}.svg` — the file of a payment mark (groups spec §3.2). Immutable, like
 * the step's script: the address carries a hash of the file. A plate has no file and no address.
 */
final class MarkController
{
    public function __invoke(PaymentMarks $marks, string $key): Response
    {
        $mark = $marks->get($key);

        abort_if($mark->file === null, 404);

        return new Response((string) file_get_contents($mark->file), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.$mark->version.'"',
        ]);
    }
}
