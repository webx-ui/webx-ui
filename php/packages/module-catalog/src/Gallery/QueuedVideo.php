<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery;

/**
 * A video file the gallery has put on the queue rather than waited for (§5 of the video spec): a
 * direct link to an mp4 may be gigabytes, and neither a panel request nor an agent's call holds
 * on that long. The row appears — or the picture gets its video — once {@see FetchVideo} is done.
 */
final readonly class QueuedVideo
{
    public function __construct(
        public int $product,
        public string $url,
        public ?int $image = null,
    ) {}

    /**
     * @return array{queued: true, product: int, url: string, image: int|null}
     */
    public function toArray(): array
    {
        return ['queued' => true, 'product' => $this->product, 'url' => $this->url, 'image' => $this->image];
    }
}
