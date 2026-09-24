<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type whose value means something even when nothing was ever written.
 *
 * A block keeps only the values somebody set, and its template reads every other field as null.
 * For most types that is the right reading — no picture is no picture. For `wx-collection` it is
 * not: a FAQ block put on a page and left alone is "every question", and a template handed null
 * would print an empty list, or fail on its own sample and never be published. The renderer asks
 * a type that says this for its reading of a value that is not there, and no other type — what an
 * existing template prints for a missing number or switch stays exactly what it printed.
 */
interface ResolvesMissing extends FieldType {}
