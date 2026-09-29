<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Popularity;

/**
 * Every signal of popularity, by key (§7.8).
 */
final class PopularitySignals
{
    /** @var array<string, PopularitySignal> */
    private array $signals = [];

    public function register(PopularitySignal $signal): void
    {
        $this->signals[$signal->key()] = $signal;
    }

    /** @return list<PopularitySignal> */
    public function all(): array
    {
        return array_values($this->signals);
    }

    /**
     * Every signal's value for a batch, by product and then by signal.
     *
     * @param  list<int>  $productIds
     * @return array<int, array<string, float>>
     */
    public function values(array $productIds): array
    {
        $values = array_fill_keys($productIds, []);

        foreach ($this->signals as $key => $signal) {
            foreach ($signal->values($productIds) as $id => $value) {
                if (isset($values[$id])) {
                    $values[$id][$key] = (float) $value;
                }
            }
        }

        return $values;
    }
}
