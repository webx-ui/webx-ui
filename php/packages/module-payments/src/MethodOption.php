<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Payments\Contracts\MethodType;

/**
 * A method as the buyer is offered it (spec §7.1): the key, what to read and whether it is paid
 * online. The method and its type ride along for the step and the snapshot.
 */
final readonly class MethodOption
{
    /** @param  list<string>  $marks  keys of `PaymentMarks`, in its order */
    public function __construct(
        public Method $method,
        public MethodType $type,
        public string $title,
        public ?string $note,
        public ?string $icon,
        public Kind $kind,
        public ?string $group,
        public array $marks = [],
        public bool $applePayOnly = false,
    ) {}

    public function key(): string
    {
        return $this->method->key;
    }

    /** @return array{key: string, title: string, note: string|null, icon: string|null, kind: string, group: string|null, marks: list<string>, apple_pay_only: bool} */
    public function toArray(): array
    {
        return [
            'key' => $this->method->key,
            'title' => $this->title,
            'note' => $this->note,
            'icon' => $this->icon,
            'kind' => $this->kind->value,
            'group' => $this->group,
            'marks' => $this->marks,
            'apple_pay_only' => $this->applePayOnly,
        ];
    }
}
