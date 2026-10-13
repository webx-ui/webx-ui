<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RouteTypes;

/**
 * The record's pair of markers on a page that gets the panel on the site (§8 of
 * WEBX_UI_SITE_BAR.md): `<!--wx-entity:{type}:{id}-->…<!--/wx-entity:{type}:{id}-->` around the
 * blocks of one record, so that the bar tells the page's own blocks from the header's, and an
 * article's from the related one printed beside it.
 *
 * The type is the one of the record's addresses — what the bar knows records by — and not the
 * model's class.
 */
final class SiteMarkers
{
    public static function entity(string $type, mixed $id, string $html): string
    {
        $mark = self::clean($type).':'.self::clean(is_scalar($id) ? (string) $id : '');

        return "<!--wx-entity:{$mark}-->{$html}<!--/wx-entity:{$mark}-->";
    }

    /**
     * The address type of a record: the one the page was resolved as when it is the page's own
     * record — one model may answer to several types, and the resolution knows which — or the
     * type its model is registered under. Null for a record without addresses.
     */
    public static function typeOf(Model $entity): ?string
    {
        if (! class_exists(RouteTypes::class)) {
            return null;
        }

        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;
        $resolution = $request instanceof Request ? Resolution::of($request) : null;

        if ($resolution?->type !== null && $resolution->entity instanceof Model && $resolution->entity->is($entity)) {
            return $resolution->type->type;
        }

        return $container->make(RouteTypes::class)->for($entity)?->type;
    }

    /** Only what a key may hold: nothing of a record's id or type can close the comment. */
    private static function clean(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.-]/', '', $value);
    }
}
