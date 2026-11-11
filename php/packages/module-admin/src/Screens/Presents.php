<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type whose node is written one way and drawn another: what the screen names, the panel
 * gets spelled out.
 *
 * `wx-icon-picker` with `props.source: "theme"`: the screen cannot know the names of the site's
 * theme, nor where its sprite is published, and the panel cannot ask — components do not go to the
 * API. {@see ScreenRegistry::render()} hands the node to the type on the way out, and the panel
 * gets the names and the address. The tree everything else reads — validation, the agents —
 * keeps the node as it was written.
 */
interface Presents extends FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function present(array $node): array;
}
