<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use WebxUi\Admin\AbstractModule;

final class PagesModule extends AbstractModule
{
    public function id(): string
    {
        return 'pages';
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 10;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['pages.view', 'pages.manage'];
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return ['tree' => true];
    }
}
