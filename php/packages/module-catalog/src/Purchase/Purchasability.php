<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Purchase;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

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
        $verdicts = [];
        $open = $products;

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

        return $verdicts;
    }
}
