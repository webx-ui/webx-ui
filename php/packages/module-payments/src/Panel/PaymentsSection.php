<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasVisibility;

/**
 * One entry of «Payments» in the panel's menu (spec §13).
 *
 * The navigation is one entry per module, and the section is three screens — the methods, the
 * attempts and the exchange journal — so it is three modules in one group. Only the first declares
 * the permissions; the rest open with the same ones.
 *
 * Every entry says so through {@see HasVisibility}: an entry with no permissions of its own would
 * otherwise be shown to everybody signed in. The two are the ones the section's reading routes ask
 * for; `payments.exchange` alone opens only the bodies, which are read from the journal.
 */
class PaymentsSection extends AbstractModule implements HasVisibility
{
    /** The id of the navigation group. */
    public const string GROUP = 'payments';

    /** The methods without keys, the attempts, the exchange journal without bodies, the order's tab. */
    public const string VIEW = 'payments.view';

    /** The methods: on and off, order, words, details, rules, limits — and the gateway's keys. */
    public const string MANAGE = 'payments.manage';

    /** The raw headers and bodies of the exchange (decision 47): the buyer's contacts are in them. */
    public const string EXCHANGE = 'payments.exchange';

    /** The sections in menu order: id → [dictionary key, icon]. The first is the module itself. */
    public const array SECTIONS = [
        'payments' => ['panel.sections.methods', 'sliders'],
        'payments-attempts' => ['panel.sections.attempts', 'list'],
        'payments-exchange' => ['panel.sections.exchange', 'refresh'],
    ];

    /** Where the group stands: after the orders (350) and the delivery (360). */
    public const int ORDER = 370;

    public function __construct(private readonly string $id) {}

    /** @return list<self> */
    public static function all(): array
    {
        return array_map(static fn (string $id): self => new self($id), array_keys(self::SECTIONS));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return (string) __('webx-payments::'.self::SECTIONS[$this->id][0]);
    }

    public function icon(): string
    {
        return self::SECTIONS[$this->id][1];
    }

    public function order(): int
    {
        return self::ORDER + (int) array_search($this->id, array_keys(self::SECTIONS), true);
    }

    public function group(): string
    {
        return self::GROUP;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->id === self::GROUP ? [self::VIEW, self::MANAGE, self::EXCHANGE] : [];
    }

    /** @return list<string> */
    public function visibleWith(): array
    {
        return [self::VIEW, self::MANAGE];
    }
}
