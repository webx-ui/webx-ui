<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use InvalidArgumentException;

/** The printable label of a parcel (§11.2): a link to the carrier's page, or the PDF itself. */
final readonly class Label
{
    private function __construct(
        public ?string $url,
        public ?string $pdf,
        public string $filename,
    ) {}

    public static function url(string $url): self
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new InvalidArgumentException('A label is an http(s) address.');
        }

        return new self($url, null, '');
    }

    public static function pdf(string $bytes, string $filename = 'label.pdf'): self
    {
        return new self(null, $bytes, $filename);
    }
}
