<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;

/** A card part that remembers which products it was told about, call by call. */
final class RecordingPart implements StorefrontPart
{
    /** @var list<list<int>> */
    public array $asked = [];

    public function point(): string
    {
        return 'catalog.card.badges';
    }

    public function view(): string
    {
        return 'fixture::badge';
    }

    public function prepare(Collection $products): array
    {
        $this->asked[] = array_map(intval(...), $products->modelKeys());

        return ['sale' => $products->modelKeys()];
    }
}
