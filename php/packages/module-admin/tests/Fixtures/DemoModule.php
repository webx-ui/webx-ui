<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Closure;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;

/**
 * A module with demo content, written by whoever is testing it.
 *
 * What `webx:demo` is about is the walking, the order and the journal — not what any one
 * module seeds — so the seeding itself is a closure the test hands in.
 */
final class DemoModule extends AbstractModule implements ProvidesDemo
{
    /**
     * @param  list<string>  $requires
     * @param  Closure(DemoLedger): void|null  $seeder
     */
    public function __construct(
        private readonly string $id,
        private readonly array $requires = [],
        private readonly ?Closure $seeder = null,
        private readonly int $order = 0,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function order(): int
    {
        return $this->order;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [$this->id.'.view'];
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->requires;
    }

    public function seed(DemoLedger $ledger): void
    {
        if ($this->seeder !== null) {
            ($this->seeder)($ledger);
        }
    }
}
