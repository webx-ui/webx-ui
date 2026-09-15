<?php

declare(strict_types=1);

namespace WebxUi\Routing\Formatters;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\EntitySlug;
use WebxUi\Routing\UrlNormaliser;

/**
 * `kak-vybrat-remen-7100104` — the slug with the key on the end.
 *
 * What an article or a product gets when nothing else makes it unique. A collision here is
 * structurally impossible, which is why `fail` is a safe policy for such a type even in a
 * catalogue where forty belts are called the same thing.
 *
 * The key exists only after the insert, which is why the observer writes on `created` rather
 * than on `saving` (§7).
 */
class SlugId implements PathFormatter
{
    public function format(Model $entity, string $locale): string
    {
        return UrlNormaliser::join(EntitySlug::read($entity, $locale).'-'.(string) $entity->getKey());
    }
}
