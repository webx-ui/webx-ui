<?php

declare(strict_types=1);

namespace WebxUi\Routing\Formatters;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\EntitySlug;
use WebxUi\Routing\UrlNormaliser;

/**
 * `hydraulic-oil-filter-46969598` — the slug with the article number on the end.
 *
 * `SlugId` with a number a customer can recognise: the same address is what they searched for
 * in the catalogue and what is printed on the box. An entity with no article number yet falls
 * back to the bare slug and lets the type's policy settle the collision, because refusing to
 * save a product because a supplier's feed has a blank column would stop an import dead.
 */
class SlugSku implements PathFormatter
{
    public function __construct(private readonly string $attribute = 'sku') {}

    public function format(Model $entity, string $locale): string
    {
        $slug = EntitySlug::read($entity, $locale);
        $sku = trim((string) $entity->getAttribute($this->attribute));

        return UrlNormaliser::join($sku === '' ? $slug : $slug.'-'.$sku);
    }
}
