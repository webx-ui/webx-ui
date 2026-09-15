<?php

declare(strict_types=1);

namespace WebxUi\Routing\Formatters;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\EntitySlug;
use WebxUi\Routing\UrlNormaliser;

/**
 * `about` — the slug and nothing else.
 *
 * What a rubric or a category gets: one segment at the root of the site, chosen by hand, no
 * decoration. Collisions are settled by the type's policy, and for these types that policy is
 * `fail` — an address made deliberately should not silently become `-2`.
 */
class Slug implements PathFormatter
{
    public function format(Model $entity, string $locale): string
    {
        return UrlNormaliser::key(EntitySlug::read($entity, $locale));
    }
}
