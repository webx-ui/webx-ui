<?php

declare(strict_types=1);

namespace WebxUi\Orders\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Orders\Data\OrderData;

/**
 * The contacts, the recipient, the comment or the manager note changed (§7.2, §8) — and with
 * `module-users`, the visitor the order belongs to (`user_id`). `before` holds the old values of
 * the fields that changed.
 */
final readonly class OrderDetailsChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public OrderData $order,
        /** @var list<string> */
        public array $fields,
        /** @var array<string, mixed> */
        public array $before,
        public string $source,
        public ?int $adminId,
        public CarbonImmutable $at,
    ) {}
}
