<?php

declare(strict_types=1);

namespace WebxUi\Media\UploadLinks;

use WebxUi\Media\Models\MediaUploadLink;

/** A link just made: its row, and the address to hand the agent. */
final readonly class IssuedLink
{
    public function __construct(
        public MediaUploadLink $link,
        public string $url,
    ) {}
}
