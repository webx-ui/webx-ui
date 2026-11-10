<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What a change of the checkout's extra fields did or, on a dry run, would do
 * (WEBX_UI_ORDER_FIELDS.md §7.3, §8.1): what the buyer will see because of it — a field now
 * required, gone from the page, moved — and `refusal`, a code of {@see FieldRefused}, when it
 * cannot be done. Orders placed are never touched, and the plan never says otherwise.
 */
final readonly class FieldPlan
{
    /** The field appears on the checkout. */
    public const string CREATED = 'created';

    public const string REQUIRED_ON = 'required_on';

    public const string REQUIRED_OFF = 'required_off';

    /** Switched on: the buyer sees it again. */
    public const string SHOWN = 'shown';

    /** Switched off: the buyer sees it no more, and no more the fields behind it. */
    public const string HIDDEN = 'hidden';

    /** Into another place — and the fields behind it with it. */
    public const string MOVED = 'moved';

    /** Options taken away: `removedOptions` names them; orders that chose one keep it. */
    public const string OPTIONS_REMOVED = 'options_removed';

    /** The definition goes; the orders keep their snapshot. */
    public const string DELETED = 'deleted';

    public const string REORDERED = 'reordered';

    /**
     * @param  list<string>  $effects  codes of this class, what the buyer will see
     * @param  string|null  $field  the input a refusal is about (`key`, `label`, `options`, `parent`, …)
     * @param  list<string>  $dependents  the keys of the fields shown behind the checkbox the change is about
     * @param  list<string>  $removedOptions  the values of the options taken away
     */
    public function __construct(
        public ?string $refusal = null,
        public array $effects = [],
        public ?string $field = null,
        public array $dependents = [],
        public array $removedOptions = [],
        public bool $dryRun = false,
        public ?int $id = null,
        public ?string $key = null,
    ) {}

    public function done(int $id): self
    {
        return new self($this->refusal, $this->effects, $this->field, $this->dependents, $this->removedOptions, false, $id, $this->key);
    }

    /** @return array<string, mixed> what the panel and an agent are answered */
    public function toArray(): array
    {
        return [
            'refusal' => $this->refusal,
            'effects' => $this->effects,
            'field' => $this->field,
            'dependents' => $this->dependents,
            'removed_options' => $this->removedOptions,
            'dry_run' => $this->dryRun,
            'id' => $this->id,
            'key' => $this->key,
            'orders_touched' => false,
        ];
    }
}
