<?php

declare(strict_types=1);

namespace WebxUi\Orders\Demo;

use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\PurchasableLine;

/**
 * The made-up goods of the demo orders (spec §15): the provider `demo.item`, registered for the
 * seed only. An order is a snapshot, so nothing reads the provider once the orders are made —
 * the panel and the buyer's page show the names and prices frozen in `order_items`.
 */
final class DemoItems implements Purchasable
{
    public const string TYPE = 'demo.item';

    public const int A = 1;

    public const int B = 2;

    public const int C = 3;

    /** The bundle «Set D»; its two parts are the children of the cart's line. */
    public const int D = 4;

    public const int D_PART_ONE = 5;

    public const int D_PART_TWO = 6;

    /** @var array<int, array{sku: string, price: int, names: array<string, string>}> */
    private const ITEMS = [
        self::A => ['sku' => 'DEMO-A', 'price' => 249000, 'names' => ['en' => 'Item A', 'ru' => 'Товар A']],
        self::B => ['sku' => 'DEMO-B', 'price' => 89000, 'names' => ['en' => 'Item B', 'ru' => 'Товар B']],
        self::C => ['sku' => 'DEMO-C', 'price' => 45000, 'names' => ['en' => 'Item C', 'ru' => 'Товар C']],
        self::D => ['sku' => 'DEMO-D', 'price' => 250000, 'names' => ['en' => 'Set D', 'ru' => 'Набор D']],
        self::D_PART_ONE => ['sku' => 'DEMO-D1', 'price' => 35000, 'names' => ['en' => 'Set D: part 1', 'ru' => 'Набор D: часть 1']],
        self::D_PART_TWO => ['sku' => 'DEMO-D2', 'price' => 15000, 'names' => ['en' => 'Set D: part 2', 'ru' => 'Набор D: часть 2']],
    ];

    public function __construct(private readonly string $currency) {}

    /** The price of one, as the provider answers it — what a refund of a line gives back. */
    public static function price(int $ref): int
    {
        return self::ITEMS[$ref]['price'];
    }

    public function resolve(array $refs, string $locale): array
    {
        $found = [];

        foreach ($refs as $ref) {
            $item = self::ITEMS[$ref] ?? null;

            if ($item === null) {
                continue;
            }

            $found[$ref] = new PurchasableLine(
                name: $item['names'][$locale] ?? $item['names']['en'],
                sku: $item['sku'],
                price: $item['price'],
                currency: $this->currency,
                shippable: true,
                names: $item['names'],
            );
        }

        return $found;
    }
}
