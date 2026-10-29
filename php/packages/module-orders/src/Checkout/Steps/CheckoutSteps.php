<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Steps;

use InvalidArgumentException;
use WebxUi\Orders\Checkout\CheckoutContext;

/**
 * The steps of the checkout by position (spec §20.2): contacts 100, delivery 200, payment 300,
 * comment 900, a project's own in between. The confirmation is not one of them — it is the column
 * of the totals (§23), always last and never removed.
 */
final class CheckoutSteps
{
    /** The key the confirmation goes by: no step may take it. */
    public const string CONFIRM = 'confirm';

    /** @var array<string, array{step: Step, position: int}> */
    private array $steps = [];

    /** A step with a key that exists replaces it: a project overrides 'contacts' this way. */
    public function register(Step $step, int $position): void
    {
        if ($step->key() === self::CONFIRM || preg_match('/^[a-z][a-z0-9-]{0,31}$/', $step->key()) !== 1) {
            throw new InvalidArgumentException("A step of the checkout cannot be called \"{$step->key()}\": lowercase letters, digits and dashes, not \"confirm\".");
        }

        $this->steps[$step->key()] = ['step' => $step, 'position' => $position];
    }

    public function remove(string $key): void
    {
        unset($this->steps[$key]);
    }

    /** @return list<Step> in position order, filtered by applies() */
    public function for(CheckoutContext $context): array
    {
        return array_values(array_filter($this->all(), static fn (Step $step): bool => $step->applies($context)));
    }

    /** The step of this key, if it is registered and shown for this checkout. */
    public function find(string $key, CheckoutContext $context): ?Step
    {
        $step = $this->steps[$key]['step'] ?? null;

        return $step !== null && $step->applies($context) ? $step : null;
    }

    /** @return list<Step> every registered step in position order, shown or not */
    public function all(): array
    {
        $steps = $this->steps;
        // Equal positions keep the order they were registered in.
        uasort($steps, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return array_values(array_column($steps, 'step'));
    }

    /** @return array<string, int> the position of every registered step by its key */
    public function positions(): array
    {
        return array_map(static fn (array $entry): int => $entry['position'], $this->steps);
    }
}
