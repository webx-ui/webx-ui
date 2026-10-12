<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

use InvalidArgumentException;

/**
 * Which parts of the Contacts tab a site draws — `webx-settings.contacts.sections`.
 *
 * A section left out is hidden, not cut out: its nodes stay in the tree with `visible: false`, so
 * a project's patch that still removes or moves them keeps applying, the write side still knows
 * the keys, and `contacts()` returns whatever is stored. Only the form stops asking an editor
 * about fields the site never shows.
 */
final class ContactSections
{
    /** What a site gets unless it asks for more: what nearly every site prints. */
    public const array DEFAULT = ['phones', 'emails', 'addresses'];

    /** Every section, with the nodes of the reference screen it draws. */
    public const array NODES = [
        'phones' => ['contacts-phones'],
        'emails' => ['contacts-emails'],
        'addresses' => ['contacts-addresses'],
        // The special dates and the time zone mean nothing without the week's hours.
        'hours' => ['contacts-hours', 'contacts-hours-exceptions', 'contacts-timezone'],
        'messengers' => ['contacts-messengers'],
        'socials' => ['contacts-socials'],
    ];

    /** The cards of the tab, by the sections in them: a card with none of them drawn is hidden whole. */
    public const array CARDS = [
        'contacts-phones-card' => ['phones', 'emails'],
        'contacts-addresses-card' => ['addresses'],
        'contacts-hours-card' => ['hours'],
        'contacts-channels-card' => ['messengers', 'socials'],
    ];

    /**
     * The patch that hides what `$sections` leaves out, in the order of the reference screen.
     *
     * @param  list<string>  $sections
     * @return list<array<string, mixed>>
     */
    public static function patch(array $sections): array
    {
        $unknown = array_diff($sections, array_keys(self::NODES));

        if ($unknown !== []) {
            // Loud on purpose: a misspelt section would otherwise hide itself without a word.
            throw new InvalidArgumentException(sprintf(
                'webx-settings.contacts.sections: unknown section(s) %s; known are %s.',
                implode(', ', $unknown),
                implode(', ', array_keys(self::NODES)),
            ));
        }

        if ($sections === []) {
            return [['op' => 'set', 'target' => 'contacts', 'visible' => false]];
        }

        $patch = [];

        foreach (self::CARDS as $card => $inside) {
            $drawn = array_intersect($inside, $sections);

            if ($drawn === []) {
                $patch[] = ['op' => 'set', 'target' => $card, 'visible' => false];

                continue;
            }

            foreach (array_diff($inside, $drawn) as $section) {
                foreach (self::NODES[$section] as $node) {
                    $patch[] = ['op' => 'set', 'target' => $node, 'visible' => false];
                }
            }
        }

        return $patch;
    }

    /**
     * What the config asks for: a list as given, anything else the default.
     *
     * @return list<string>
     */
    public static function configured(mixed $sections): array
    {
        return is_array($sections) ? array_values(array_map(strval(...), $sections)) : self::DEFAULT;
    }
}
