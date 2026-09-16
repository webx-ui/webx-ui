<?php

declare(strict_types=1);

namespace WebxUi\Seo;

/**
 * The names of the fields a page can be given, said once.
 *
 * A rule for an address, the meta of one entity, the card that edits either, and the request
 * that carries a rule all name the same set. Four spellings of it is three of them drifting —
 * and a field that reaches the form but not the table looks like a save that silently loses
 * what was typed.
 *
 * A class rather than constants on the trait beside it: a trait constant cannot be read from
 * outside the classes using it, which is exactly what everything here needs to do.
 */
final class Fields
{
    /** What the card is called on a screen — the node of type `wx-seo` carries this name. */
    public const SCREEN = 'seo';

    /** Held as a language map, `{"en": "…", "ru": "…"}`. */
    public const TRANSLATED = ['title', 'h1', 'description', 'keywords', 'og_title', 'og_description'];

    /** The rest, stored as they come. */
    public const PLAIN = ['og_image', 'canonical', 'robots', 'json_ld'];
}
