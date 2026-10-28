<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use WebxUi\Cart\Contracts\ResolvesWithOptions;

/**
 * Asks a type's provider about a batch of lines in one call. A provider with options gets the
 * lines with their options; a plain one gets the distinct ids. A type nobody registers answers
 * null for every line — the line is gone, as a deleted record is.
 */
final class Resolver
{
    public function __construct(private readonly Purchasables $purchasables) {}

    /**
     * @param  list<CartRef>  $lines  of one type
     * @return array<string, PurchasableLine|null> CartRef::$key → line
     */
    public function resolve(string $type, array $lines, string $locale): array
    {
        $answer = array_fill_keys(array_map(static fn (CartRef $line): string => $line->key, $lines), null);

        if ($lines === [] || ! $this->purchasables->has($type)) {
            return $answer;
        }

        $provider = $this->purchasables->get($type);

        if ($provider instanceof ResolvesWithOptions) {
            $found = $provider->resolveLines($lines, $locale);

            foreach ($lines as $line) {
                $answer[$line->key] = $this->line($found[$line->key] ?? null);
            }

            return $answer;
        }

        $refs = array_values(array_unique(array_map(static fn (CartRef $line): int => $line->ref, $lines)));
        $found = $provider->resolve($refs, $locale);

        foreach ($lines as $line) {
            $answer[$line->key] = $this->line($found[$line->ref] ?? null);
        }

        return $answer;
    }

    private function line(mixed $line): ?PurchasableLine
    {
        return $line instanceof PurchasableLine ? $line : null;
    }
}
