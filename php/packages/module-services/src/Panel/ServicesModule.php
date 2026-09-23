<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

/**
 * Where services are written, ordered and published (§4.6, §4.9).
 *
 * The panel's usual pair: `services.view` opens the list, `services.manage` writes — including the
 * order, which is a decision about the catalogue as much as a title is.
 */
final class ServicesModule extends ServicesGroup
{
    public function id(): string
    {
        return 'services';
    }

    public function title(): string
    {
        return (string) __('webx-services::module.services');
    }

    public function icon(): string
    {
        return 'list';
    }

    public function order(): int
    {
        return 400;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['services.view', 'services.manage'];
    }
}
