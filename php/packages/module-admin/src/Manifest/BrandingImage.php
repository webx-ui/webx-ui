<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

/**
 * A picture the panel wears: an address and, when the source knows them, the size it was made
 * at. The size is not decoration — the corner of a panel that has already drawn its menu is
 * exactly where a picture arriving without dimensions shoves everything sideways.
 */
final readonly class BrandingImage
{
    public function __construct(
        public string $url,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    /**
     * @return array{url: string, width: int|null, height: int|null}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
