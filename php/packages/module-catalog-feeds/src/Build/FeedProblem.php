<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

/**
 * Why a feed cannot be kept, under the field it belongs to (§8.1 of the feeds spec). A mistake found
 * in the sample also says where: the line of the sample, the part it fell in and the line of that
 * part, the product and the text around the place.
 */
final readonly class FeedProblem
{
    public function __construct(
        public string $field,
        public string $message,
        public ?int $line = null,
        public ?string $part = null,
        public ?int $partLine = null,
        public ?int $product = null,
        public ?string $fragment = null,
    ) {}

    /**
     * @return array{field: string, message: string, line: int|null, part: string|null, part_line: int|null, product: int|null, fragment: string|null}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'message' => $this->message,
            'line' => $this->line,
            'part' => $this->part,
            'part_line' => $this->partLine,
            'product' => $this->product,
            'fragment' => $this->fragment,
        ];
    }
}
