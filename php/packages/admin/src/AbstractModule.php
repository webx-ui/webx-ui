<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Support\Str;
use WebxUi\Admin\Contracts\Module;

/**
 * Defaults for a module, so a small one is a few lines.
 *
 * Only `id()` has no sensible default; the title is derived from it until someone says
 * otherwise.
 */
abstract class AbstractModule implements Module
{
    public function title(): string
    {
        return Str::headline($this->id());
    }

    public function icon(): ?string
    {
        return null;
    }

    public function order(): int
    {
        return 0;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [];
    }
}
