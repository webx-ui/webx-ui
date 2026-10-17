<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type that loads ahead what resolving its values would otherwise load one at a time —
 * the library rows behind a page of pictures. Given every stored value of its type a page
 * holds, once, before any of them is resolved: a page of seven picture blocks asked the library
 * seven times, once per block, and asks it once with this.
 */
interface PreloadsValues extends FieldType
{
    /**
     * @param  list<mixed>  $stored  the values as they are kept, in the language being read
     */
    public function preload(array $stored): void;
}
