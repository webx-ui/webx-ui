<?php

declare(strict_types=1);

namespace WebxUi\Routing\Formatters;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\EntitySlug;
use WebxUi\Routing\Exceptions\RoutingException;
use WebxUi\Routing\UrlNormaliser;

/**
 * `about/mission` — the slugs of every ancestor, root first, then the node's own.
 *
 * What a page gets: the address repeats the structure an editor already sees in the tree, so
 * moving a page under another one is the same gesture as changing its address. Every descendant
 * moves with it, and every one of them leaves an alias behind (§7).
 */
class TreePath implements PathFormatter
{
    public function format(Model $entity, string $locale): string
    {
        if (! method_exists($entity, 'pathFromRoot')) {
            throw new RoutingException(sprintf(
                '%s is formatted by TreePath but is not a tree: add the HasNestedSet trait, or give the type a different formatter.',
                $entity::class,
            ));
        }

        $segments = [];

        // One query per node. The expensive case — a branch that has just moved — reloads its
        // descendants anyway, and a page tree is shallow; if this ever shows up in a profile,
        // the fix is to hand the ancestors in, not to cache them here.
        foreach ($entity->pathFromRoot() as $node) {
            $segments[] = EntitySlug::read($node, $locale);
        }

        return UrlNormaliser::join(...$segments);
    }
}
