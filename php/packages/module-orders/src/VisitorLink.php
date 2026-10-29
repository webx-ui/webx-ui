<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Admin\Visitors\Visitor;

/**
 * What `linkVisitor()` and `unlinkVisitor()` answer (spec §13.3) — the same for a dry run, which
 * the panel's dialog and `orders_link_visitor` show before anything is written: whom the order
 * goes to, whom it belonged to before, and whether anything changes at all.
 */
final readonly class VisitorLink
{
    /** The order now belongs to `visitor` — linked, or moved from `previous` in one step. */
    public const string LINKED = 'linked';

    /** The order is a guest's again; `previous` was its visitor. */
    public const string UNLINKED = 'unlinked';

    /** It was so already: nothing is written, no line, no event. */
    public const string UNCHANGED = 'unchanged';

    public function __construct(
        public string $outcome,
        public ?Visitor $visitor,
        public ?int $previousId,
        public ?Visitor $previous,
        public bool $dryRun = false,
    ) {}

    public function changed(): bool
    {
        return $this->outcome !== self::UNCHANGED;
    }

    /** «Anna Petrova <anna@example.test>» — how the history and the journal name a visitor. */
    public static function name(Visitor $visitor): string
    {
        return $visitor->name().' <'.$visitor->email.'>';
    }

    /**
     * The answer of the panel and of the agent's tool.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $visitor = static fn (?Visitor $visitor): ?array => $visitor === null ? null : [
            'id' => $visitor->id,
            'name' => $visitor->name(),
            'email' => $visitor->email,
        ];

        return [
            'outcome' => $this->outcome,
            'visitor' => $visitor($this->visitor),
            'previous' => $visitor($this->previous) ?? ($this->previousId !== null ? ['id' => $this->previousId, 'name' => null, 'email' => null] : null),
            'dry_run' => $this->dryRun,
        ];
    }
}
