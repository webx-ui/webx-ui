<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** Something the visitor should know: the cart reports it, it never quietly mends it. */
final class Warning
{
    public const string LINE_GONE = 'line.gone';

    public const string LINE_UNAVAILABLE = 'line.unavailable';

    public const string LINE_CURRENCY = 'line.currency';

    public const string LINE_QTY_LIMITED = 'line.qty_limited';

    public const string PRICE_DOWN = 'price.down';

    public const string PRICE_UP = 'price.up';

    public const string TENDERS_CAPPED = 'tenders.capped';

    public const string SOURCE_FAILED = 'totals.source_failed';

    public const array CODES = [
        self::LINE_GONE,
        self::LINE_UNAVAILABLE,
        self::LINE_CURRENCY,
        self::LINE_QTY_LIMITED,
        self::PRICE_DOWN,
        self::PRICE_UP,
        self::TENDERS_CAPPED,
        self::SOURCE_FAILED,
    ];

    /** @param  int|string|null  $line  the line's id; null — the cart */
    public function __construct(
        public readonly string $code,
        public readonly int|string|null $line,
        public readonly string $message,
    ) {}

    /** @return array{code: string, line: int|string|null, message: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'line' => $this->line, 'message' => $this->message];
    }
}
