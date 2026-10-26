<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Catalog;

use Illuminate\Http\Request;

/**
 * Where a configurator starts, carried in the address of its page (§22.3):
 *
 *     /shower-configurator?configurator=shower&preset[construction]=walk-in&preset[h]=2000#webx-configurator-shower
 *
 * `configurator` names the one it is for — a page may hold two, and the other one starts as its
 * block says. `preset` is what a block's preset is: a value for a step, a number for a size. It is
 * only a wish: the server reads it as the schema's `initial` would be read — an unknown key or value
 * is dropped, a size is clamped into its limits — so nothing from the address reaches a price
 * without the engine resolving it first. The anchor scrolls to the block.
 */
final class PresetAddress
{
    public const string KEY = 'configurator';

    public const string PRESET = 'preset';

    /**
     * The address of the page with the configurator and its preset in it.
     *
     * @param  array<string, string|int|float>|null  $preset
     */
    public static function to(string $page, string $key, ?array $preset): string
    {
        [$address, $hash] = array_pad(explode('#', $page, 2), 2, null);
        $query = http_build_query([self::KEY => $key, self::PRESET => $preset ?? []], '', '&', PHP_QUERY_RFC3986);

        $address .= (str_contains($address, '?') ? '&' : '?').$query;

        // The page's own anchor, when the link has one, is where somebody chose to land.
        return $address.'#'.($hash !== null && $hash !== '' ? $hash : 'webx-configurator-'.$key);
    }

    /**
     * The preset of the address for this configurator, or an empty one: another key, no key, or
     * nothing a preset could be.
     *
     * @return array<string, string|int|float>
     */
    public static function from(Request $request, string $key): array
    {
        if ($request->query(self::KEY) !== $key) {
            return [];
        }

        return ConfiguratorPart::preset($request->query(self::PRESET)) ?? [];
    }
}
