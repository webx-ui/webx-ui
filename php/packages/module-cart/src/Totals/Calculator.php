<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

use Illuminate\Contracts\Translation\Translator;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Cart\CartRef;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Money;
use WebxUi\Cart\PurchasableLine;
use WebxUi\Cart\Resolver;
use WebxUi\Localization\Locales;

/**
 * The one place the chain is counted (WEBX_UI_COMMERCE.md, principle 5):
 *
 *     lines → generated lines → subtotal → adjustments → total → tenders → payable
 *     and beside them benefits and hints, which change no sum.
 *
 * It reads and never writes: a line over its stock, a gone record, a price in another currency
 * are warnings in the state, not quiet repairs. Each type's provider is asked once per batch of
 * lines. A source that throws is skipped with a warning — except when an order is being made,
 * which must never come from a chain with a link missing.
 */
final class Calculator
{
    public function __construct(
        private readonly Resolver $resolver,
        private readonly LineSources $lineSources,
        private readonly AdjustmentSources $adjustmentSources,
        private readonly TenderSources $tenderSources,
        private readonly BenefitSources $benefitSources,
        private readonly HintSources $hintSources,
        private readonly CurrencySource $currencies,
        private readonly Money $money,
        private readonly Locales $locales,
        private readonly Translator $translator,
        private readonly LoggerInterface $log,
    ) {}

    public function calculate(Cart $cart, string $locale, Purpose $purpose = Purpose::Cart): CartState
    {
        $info = new CartInfo($cart->id, $locale, $cart->currency, $cart->declinedRules());

        /** @var list<CartItem> $items */
        $items = $cart->items()->get()->all();

        return $this->count($info, $items, $purpose);
    }

    /** What a visitor without a cart sees: the same shape, every sum zero. */
    public function empty(string $locale): CartState
    {
        return $this->count(new CartInfo(null, $locale, $this->currencies->currency()), [], Purpose::Cart);
    }

    /** @param  list<CartItem>  $items */
    private function count(CartInfo $info, array $items, Purpose $purpose): CartState
    {
        $warnings = [];
        $lines = $this->stored($info, $items, $warnings);
        $context = new CartContext($info, $lines, $this->subtotal($lines), $purpose);

        // 2. Generated lines, less the ones the visitor declined.
        $lines = [...$lines, ...$this->generated($context, $warnings)];
        $subtotal = $this->subtotal($lines);
        $context = $context->withLines($lines, $subtotal);

        // 4. Adjustments, each source seeing what came before it.
        $adjustments = [];
        $total = $subtotal;

        foreach ($this->adjustmentSources->all() as $source) {
            $found = $this->ask($source, $context, $warnings, static fn (): array => $source->adjustments($context));

            foreach ($found as $adjustment) {
                if ($adjustment instanceof Adjustment) {
                    $adjustments[] = $adjustment;
                    $total += $adjustment->amount;
                }
            }

            $context = $context->withAdjustments($adjustments, max(0, $total));
        }

        $total = max(0, $total);
        $context = $context->withAdjustments($adjustments, $total);

        // 5. Tenders; together never more than the total — the rest is cut off.
        $tenders = [];
        $left = $total;
        $capped = false;

        foreach ($this->tenderSources->all() as $source) {
            $found = $this->ask($source, $context, $warnings, static fn (): array => $source->tenders($context));

            foreach ($found as $tender) {
                if (! $tender instanceof Tender || $tender->amount <= 0) {
                    continue;
                }

                if ($tender->amount > $left) {
                    $capped = true;
                    $tender = $tender->withAmount($left);
                }

                if ($tender->amount > 0) {
                    $tenders[] = $tender;
                    $left -= $tender->amount;
                }
            }

            $context = $context->withTenders($tenders);
        }

        if ($capped) {
            $warnings[] = $this->warning(Warning::TENDERS_CAPPED, null, $info->locale);
        }

        // 7. Beside the sums.
        $benefits = [];

        foreach ($this->benefitSources->all() as $source) {
            foreach ($this->ask($source, $context, $warnings, static fn (): array => $source->benefits($context)) as $benefit) {
                if ($benefit instanceof Benefit) {
                    $benefits[] = $benefit;
                }
            }
        }

        $hints = [];

        foreach ($this->hintSources->all() as $source) {
            foreach ($this->ask($source, $context, $warnings, static fn (): array => $source->hints($context)) as $hint) {
                if ($hint instanceof Hint) {
                    $hints[] = $hint;
                }
            }
        }

        $byLine = [];

        foreach ($adjustments as $adjustment) {
            if ($adjustment->line !== null) {
                $byLine[(string) $adjustment->line][] = $adjustment;
            }
        }

        return new CartState(
            currency: $info->currency,
            locale: $info->locale,
            lines: $lines,
            lineAdjustments: $byLine,
            subtotal: $subtotal,
            adjustments: $adjustments,
            total: $total,
            tenders: $tenders,
            payable: $total - array_sum(array_map(static fn (Tender $tender): int => $tender->amount, $tenders)),
            benefits: $benefits,
            hints: $hints,
            warnings: $warnings,
            money: $this->money,
        );
    }

    /**
     * Step 1: the stored lines with what their providers say now, parents before children.
     *
     * @param  list<CartItem>  $items
     * @param  list<Warning>  $warnings
     * @return list<ResolvedLine>
     */
    private function stored(CartInfo $info, array $items, array &$warnings): array
    {
        $byType = [];

        foreach ($items as $item) {
            $byType[$item->type][] = new CartRef((string) $item->id, $item->ref_id, $item->options ?? []);
        }

        $answers = [];

        foreach ($byType as $type => $refs) {
            $answers += $this->resolver->resolve($type, $refs, $info->locale);
        }

        $effective = [];
        $lines = [];

        // Roots first, so a child knows its parent's effective qty; the list keeps id order.
        foreach ([false, true] as $children) {
            foreach ($items as $item) {
                if (($item->parent_id !== null) !== $children) {
                    continue;
                }

                $parentQty = $item->parent_id === null ? 1 : ($effective[$item->parent_id] ?? 1);
                $line = $answers[(string) $item->id] ?? null;
                $lines[$item->id] = $this->resolved($info, $item->id, $item->type, $item->ref_id, $item->qty, $parentQty, $item->parent_id, $line, $item->options, $item->name_at_add, $item->price_at_add, null, $warnings);
                $effective[$item->id] = $lines[$item->id]->effectiveQty;
            }
        }

        ksort($lines);

        return array_values($lines);
    }

    /**
     * Step 2: what the line sources add, less what the visitor declined. A line under a line
     * that has a parent itself would be a third level: it goes to the root, and the log says so.
     *
     * @param  list<Warning>  $warnings
     * @return list<ResolvedLine>
     */
    private function generated(CartContext $context, array &$warnings): array
    {
        $wanted = [];

        foreach ($this->lineSources->all() as $source) {
            $numbers = [];

            foreach ($this->ask($source, $context, $warnings, static fn (): array => $source->lines($context)) as $generated) {
                if (! $generated instanceof GeneratedLine || in_array($generated->source, $context->cart->declinedRules, true)) {
                    continue;
                }

                $number = $numbers[$generated->source] = ($numbers[$generated->source] ?? -1) + 1;
                $wanted["{$generated->source}:{$number}"] = $generated;
            }
        }

        $byType = [];

        foreach ($wanted as $key => $generated) {
            $byType[$generated->type][] = new CartRef($key, $generated->refId, []);
        }

        $answers = [];

        foreach ($byType as $type => $refs) {
            $answers += $this->resolver->resolve($type, $refs, $context->cart->locale);
        }

        $lines = [];

        foreach ($wanted as $key => $generated) {
            $parentId = $generated->parentId;

            if ($parentId !== null) {
                $parent = $context->line($parentId);

                if ($parent === null || $parent->parentId !== null) {
                    $this->log->warning("Cart: the generated line {$key} asked for line {$parentId} as its parent; it went to the root, the cart is one level deep.");
                    $parentId = null;
                }
            }

            $line = $answers[$key] ?? null;
            $lines[] = $this->resolved(
                $context->cart, $key, $generated->type, $generated->refId, $generated->qty, 1, $parentId, $line, null,
                $line !== null ? [$context->cart->locale => $line->name] : [], null, $generated, $warnings,
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @param  array<string, string>  $snapshot
     * @param  list<Warning>  $warnings
     */
    private function resolved(
        CartInfo $info,
        int|string $key,
        string $type,
        int $ref,
        int $qty,
        int $parentQty,
        ?int $parentId,
        ?PurchasableLine $line,
        ?array $options,
        array $snapshot,
        ?int $priceAtAdd,
        ?GeneratedLine $generated,
        array &$warnings,
    ): ResolvedLine {
        // A generated line counts as the rule says, not per unit of the line it stands under.
        $effective = $generated === null ? $qty * $parentQty : $qty;
        $name = $line !== null ? $line->name : $this->snapshotName($snapshot, $info->locale);
        $available = false;
        $price = null;

        if ($line === null) {
            $warnings[] = $this->warning(Warning::LINE_GONE, $key, $info->locale, $name);
        } else {
            $price = $generated->price ?? $line->price;

            if ($info->currency === null || $line->currency !== $info->currency) {
                $warnings[] = $this->warning(Warning::LINE_CURRENCY, $key, $info->locale, $name);
            } elseif (! $line->available || $price <= 0) {
                $warnings[] = $this->warning(Warning::LINE_UNAVAILABLE, $key, $info->locale, $name);
            } else {
                $available = true;
            }

            if ($line->maxQty !== null && $effective > $line->maxQty) {
                $effective = max(0, $line->maxQty);
                $warnings[] = $this->warning(Warning::LINE_QTY_LIMITED, $key, $info->locale, $name, ['max' => $effective]);
            }

            if ($available && $priceAtAdd !== null && $price !== $priceAtAdd) {
                $warnings[] = $this->warning($price < $priceAtAdd ? Warning::PRICE_DOWN : Warning::PRICE_UP, $key, $info->locale, $name);
            }
        }

        return new ResolvedLine(
            key: $key,
            type: $type,
            refId: $ref,
            qty: $qty,
            effectiveQty: $effective,
            parentId: $parentId,
            line: $line,
            source: $generated?->source,
            options: $options === [] ? null : $options,
            name: $name,
            price: $price,
            priceAtAdd: $priceAtAdd,
            available: $available,
            declinable: $generated !== null && $generated->declinable,
            label: $generated?->label,
        );
    }

    /**
     * The name a gone line kept: in the language of the request, else the site's default, else
     * whichever there is.
     *
     * @param  array<string, string>  $snapshot
     */
    private function snapshotName(array $snapshot, string $locale): string
    {
        foreach ([$locale, $this->locales->defaultCode()] as $code) {
            if (isset($snapshot[$code]) && $snapshot[$code] !== '') {
                return $snapshot[$code];
            }
        }

        foreach ($snapshot as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /** @param  list<ResolvedLine>  $lines */
    private function subtotal(array $lines): int
    {
        return array_sum(array_map(static fn (ResolvedLine $line): int => $line->amount(), $lines));
    }

    /**
     * A source's answer, or nothing with a warning when it throws — unless an order is being made.
     *
     * @param  list<Warning>  $warnings
     * @param  callable(): array<mixed>  $call
     * @return array<mixed>
     */
    private function ask(object $source, CartContext $context, array &$warnings, callable $call): array
    {
        try {
            return $call();
        } catch (Throwable $e) {
            if ($context->purpose === Purpose::Order) {
                throw $e;
            }

            $this->log->error('Cart: the source '.$source::class.' failed and was skipped.', ['exception' => $e]);
            $warnings[] = $this->warning(Warning::SOURCE_FAILED, null, $context->cart->locale);

            return [];
        }
    }

    /** @param  array<string, int|string>  $replace */
    private function warning(string $code, int|string|null $line, string $locale, string $name = '', array $replace = []): Warning
    {
        $message = $this->translator->get("webx-cart::warnings.{$code}", ['name' => $name, ...$replace], $locale);

        return new Warning($code, $line, is_string($message) ? $message : $code);
    }
}
