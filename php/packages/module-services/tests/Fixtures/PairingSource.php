<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests\Fixtures;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;

/**
 * Records of some other module that can be related to services — what recipes will be. Hands
 * back nothing of its own; it remembers the choice it was asked with, which is what a test of the
 * doors and of the page has to see.
 */
final class PairingSource implements CollectionSource
{
    public ?Selection $asked = null;

    public function key(): string
    {
        return 'pairings';
    }

    public function title(): string
    {
        return 'Pairings';
    }

    public function categories(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['service'];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function items(Selection $selection, string $locale): array
    {
        $this->asked = $selection;

        return [['id' => 1, 'anchor' => 'pairing-1', 'categories' => [], 'text' => 'Tea']];
    }
}
