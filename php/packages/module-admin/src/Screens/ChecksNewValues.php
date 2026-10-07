<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type with a check that only a value being written for the first time has to pass.
 *
 * The case it is for: a picture deleted from the library must not stop a page from being saved —
 * the field draws it as broken and the editor takes it out — but a library key nobody ever had,
 * typed by an agent rather than picked, is a value the site prints as an image with no address.
 * The two look the same to {@see FieldType::rules()}. Whoever writes knows which values it already
 * held, and asks this only of the others.
 */
interface ChecksNewValues
{
    /**
     * What is wrong with a value that was not there before, in words — empty when nothing is.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array;
}
