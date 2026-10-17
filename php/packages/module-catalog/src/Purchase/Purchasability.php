<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Purchase;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Support\RequestMemo;

/**
 * Can this be bought — the one question a cart will ask the catalogue (§7.5, §4.4 of the
 * architecture). Until there is a cart the product page and MCP ask it.
 *
 * The rules are asked in the order they registered and the first refusal is the answer. The core
 * registers "not on sale" first, since nothing else matters about a product that is not sold,
 * and "price on request" as a last word, asked after every satellite whenever it registered —
 * "out of stock" says more.
 */
final class Purchasability
{
    /** The request memo of the verdicts, by product id. */
    private const MEMO = 'product.verdict.';

    /** @var list<PurchaseRule> */
    private array $rules = [];

    /** @var list<PurchaseRule> */
    private array $last = [];

    public function register(PurchaseRule $rule, bool $last = false): void
    {
        if ($last) {
            $this->last[] = $rule;

            return;
        }

        $this->rules[] = $rule;
    }

    public function for(Product $product): Verdict
    {
        return $this->forMany(new Collection([$product]))[(int) $product->id] ?? Verdict::yes();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, Verdict>
     */
    public function forMany(Collection $products): array
    {
        // Once a request per product: the page, the markup and the search document of the same
        // product ask the same rules; forgotten by any save of a product or of what a rule
        // reads (`forget()`).
        $verdicts = [];
        $open = $products->reject(function (Product $product) use (&$verdicts): bool {
            $known = RequestMemo::remember(self::MEMO.$product->getKey(), static fn (): ?Verdict => null);

            if ($known instanceof Verdict) {
                $verdicts[(int) $product->id] = $known;
            }

            return $known instanceof Verdict;
        })->values();
        $asked = $open;

        foreach ([...$this->rules, ...$this->last] as $rule) {
            if ($open->isEmpty()) {
                break;
            }

            foreach ($rule->refuse($open) as $id => $verdict) {
                $verdicts[(int) $id] ??= $verdict;
            }

            // A product refused once is not asked about again: the first refusal is the answer.
            $open = $open->reject(static fn (Product $product): bool => isset($verdicts[(int) $product->id]))->values();
        }

        foreach ($products as $product) {
            $verdicts[(int) $product->id] ??= Verdict::yes();
        }

        foreach ($asked as $product) {
            RequestMemo::forget(self::MEMO.$product->getKey());
            RequestMemo::remember(self::MEMO.$product->getKey(), static fn (): Verdict => $verdicts[(int) $product->id]);
        }

        return $verdicts;
    }

    /** What a rule reads changed in this request — a status put on a product: the verdicts are stale. */
    public function forget(): void
    {
        RequestMemo::forget(self::MEMO);
    }
}
