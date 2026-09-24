<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type whose node may have nothing to edit on this site, and then leaves the screen.
 *
 * `wx-relations` pointing at services, on a site without the services module: the field would be
 * a picker with nothing in it. {@see ScreenRegistry::tree()} drops such a node before anybody
 * reads the screen — the panel does not draw it, a save does not accept it, and what the record
 * already holds under that name is left exactly as it was, for the module to find when it is back.
 */
interface Withdraws extends FieldType
{
    /**
     * @param  array<string, mixed>  $node
     */
    public function withdrawn(array $node): bool;
}
