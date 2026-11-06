<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * A mark as a page draws it (groups spec §3.2): a card from a file, or the words on a plate when
 * there is no file. What is known of a file is its hash and proportions, nothing of what it draws,
 * so an official file takes the place of ours without a line of code.
 */
final readonly class PaymentMark
{
    /** Every mark stands this high, a plate too: equal size is what the systems ask for (§3.3). */
    public const int HEIGHT = 24;

    /** The width of a card, and of a file that does not say its own. */
    public const int WIDTH = 38;

    public function __construct(
        public string $key,
        public string $alt,
        public ?string $file = null,
        public ?string $version = null,
        public int $width = self::WIDTH,
    ) {}

    public function isPlate(): bool
    {
        return $this->file === null;
    }

    /** The address of the file: it carries the hash, so a new file gets a new address. */
    public function url(): ?string
    {
        return $this->file === null ? null : route('webx-payments.mark', ['key' => $this->key, 'v' => $this->version]);
    }

    /** The width of a file at the height of a mark: `viewBox`, or `width` and `height` without one. */
    public static function widthOf(string $svg): int
    {
        if (preg_match('/<svg\b[^>]*>/i', $svg, $root) !== 1) {
            return self::WIDTH;
        }

        $number = '([0-9]*\.?[0-9]+)';

        if (preg_match('/\sviewBox\s*=\s*["\']\s*-?[0-9.]+[\s,]+-?[0-9.]+[\s,]+'.$number.'[\s,]+'.$number.'/i', $root[0], $box) === 1) {
            return self::scaled((float) $box[1], (float) $box[2]);
        }

        if (preg_match('/\swidth\s*=\s*["\']'.$number.'/i', $root[0], $width) === 1 && preg_match('/\sheight\s*=\s*["\']'.$number.'/i', $root[0], $height) === 1) {
            return self::scaled((float) $width[1], (float) $height[1]);
        }

        return self::WIDTH;
    }

    private static function scaled(float $width, float $height): int
    {
        return $width > 0 && $height > 0 ? (int) round(self::HEIGHT * $width / $height) : self::WIDTH;
    }
}
