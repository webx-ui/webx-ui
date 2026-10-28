<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests\Fakes;

use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;

/** A visitors module in one line: whoever the test says is signed in. */
final readonly class FakeVisitors implements Visitors
{
    public function __construct(private ?Visitor $visitor) {}

    public function current(): ?Visitor
    {
        return $this->visitor;
    }

    public function find(int $id): ?Visitor
    {
        return $this->visitor?->id === $id ? $this->visitor : null;
    }

    public function findByEmail(string $email): ?Visitor
    {
        return $this->visitor?->email === $email ? $this->visitor : null;
    }

    public function signInUrl(?string $back = null): ?string
    {
        return null;
    }
}
