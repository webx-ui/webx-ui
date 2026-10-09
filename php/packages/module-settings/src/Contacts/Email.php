<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

final class Email
{
    public function __construct(
        public readonly string $address,
        public readonly ?string $label = null,
    ) {}

    public function href(): string
    {
        return 'mailto:'.$this->address;
    }
}
