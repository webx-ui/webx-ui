<?php

declare(strict_types=1);

namespace WebxUi\Menu\Panel;

use Illuminate\Validation\Rule;
use WebxUi\Admin\Screens\Types\LinkType;
use WebxUi\Localization\Locales;
use WebxUi\Menu\Menus;

/**
 * What an item of a menu may be, whoever is sending it.
 *
 * The dialog and the agent's tools arrive at this one list rather than at two that look alike:
 * a second set of rules is the set that drifts, and the one that drifts is always the one the
 * panel is not looking at.
 *
 * Where the item points is checked by the same type a described screen uses, so a menu item and
 * a link field of a block are refused for the same reasons in the same words. What is left here
 * is everything a menu adds on top of a link — the heading flag, the look, the languages.
 */
final class ItemInput
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $menuKey): array
    {
        return [
            // Empty is the ordinary case and a property rather than laziness: an item with no
            // label of its own is called what the entity behind it is called (§4).
            'title' => ['nullable', 'array'],
            'title.*' => ['nullable', 'string', 'max:190'],

            'link' => app(LinkType::class)->rules([]),

            // Only what this menu offers. A look the site never declared would be a class its
            // markup does not divide by — that is, an item that quietly renders as any other.
            'variant' => ['sometimes', 'string', Rule::in(app(Menus::class)->variants($menuKey))],

            'is_heading' => ['sometimes', 'boolean'],
            'visible' => ['sometimes', 'boolean'],

            // Empty means every language (§7). A code the site does not publish is refused
            // rather than ignored: an item hidden everywhere looks exactly like a bug.
            'locales' => ['nullable', 'array'],
            'locales.*' => ['string', Rule::in(app(Locales::class)->codes())],

            'parent_id' => ['nullable', 'integer'],
        ];
    }
}
