<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

/**
 * Whose panel this is: the name it goes by, the logo for the open sidebar and the mark for the
 * rail. Every part is optional — a panel with none of it is the panel as it has always been,
 * its configured title in the corner.
 *
 * The two pictures are two settings rather than one picture and a cropping rule: a wordmark
 * squeezed into 56 px gives you its first two letters, and only the client knows what their
 * mark is. A mark left empty therefore means an empty rail, not a guess.
 */
final readonly class Branding
{
    public function __construct(
        public ?string $title = null,
        public ?BrandingImage $logo = null,
        public ?BrandingImage $mark = null,
    ) {}

    /**
     * @return array{logo: array{url: string, width: int|null, height: int|null}|null, mark: array{url: string, width: int|null, height: int|null}|null}
     */
    public function toArray(): array
    {
        return [
            'logo' => $this->logo?->toArray(),
            'mark' => $this->mark?->toArray(),
        ];
    }
}
