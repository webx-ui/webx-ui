<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Downloads a video from a direct link into the gallery (§5 of the video spec): onto the picture
 * it names, or into a new row of its own when it names none.
 *
 * Once: a link that answered with something that is not a video will answer the same way the
 * second time, and a refusal is the failed job an operator looks at, not a loop.
 */
final class FetchVideo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** Gigabytes over a slow line: an hour, not the queue's default minute. */
    public int $timeout = 3600;

    public function __construct(
        public readonly int $product,
        public readonly string $url,
        public readonly ?int $image = null,
    ) {}

    public function handle(Gallery $gallery): void
    {
        $gallery->download($this->product, $this->url, $this->image);
    }
}
