<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type whose one value is many values, and whose partial edit has to be laid over the
 * old one rather than put in its place.
 *
 * The panel sends such a field whole, so replacing is right there. An agent sends what it means
 * to change — `{"seo": {"title": {"en": "…"}}}` is the English title and nothing else — and a
 * replace erased the description beside it. `ScreenValues::patch` asks a type that says this for
 * the merged value; no other type is touched, so a list or a picture sent whole still replaces.
 */
interface MergesEdits extends FieldType
{
    /**
     * The current value with the edit laid over it, before it is checked and cast.
     *
     * @param  mixed  $current  What the record holds, as its form describes it.
     * @param  mixed  $sent  What the edit carries for this field.
     * @param  array<string, mixed>  $node
     */
    public function merge(mixed $current, mixed $sent, array $node): mixed;
}
