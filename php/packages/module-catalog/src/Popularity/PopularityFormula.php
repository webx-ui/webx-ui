<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Popularity;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * How signals become a score: `Σ weight × signal` (§9).
 *
 * A class in the container rather than a line in the command, so a project that means something
 * else by "popular" binds its own and keeps the rest — the recount, the fading, the touch of
 * what moved:
 *
 *     $this->app->bind(PopularityFormula::class, OurFormula::class);
 */
class PopularityFormula
{
    public function __construct(private readonly Config $config) {}

    /**
     * @param  array<string, float>  $signals  signal key → value for one product
     */
    public function score(int $productId, array $signals): float
    {
        /** @var array<string, mixed> $weights */
        $weights = (array) $this->config->get('webx-catalog.popularity.weights', []);
        $score = 0.0;

        foreach ($signals as $key => $value) {
            $weight = $weights[$key] ?? 0;
            $score += (is_numeric($weight) ? (float) $weight : 0.0) * $value;
        }

        return round($score, 4);
    }
}
