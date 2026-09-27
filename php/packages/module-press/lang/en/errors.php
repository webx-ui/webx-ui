<?php

declare(strict_types=1);

return [
    'prefix' => 'webx-press.prefix is empty: outlets need an address of their own, e.g. "press".',
    'website-url' => 'A website has to start with http:// or https://.',
    'url' => 'A link has to start with http:// or https://.',
    'target' => 'An article needs a link or a PDF.',
    'pdf' => 'This file is not a PDF.',
    'kind' => 'This kind is not one of the site\'s: :kinds.',
    'precision' => 'The date is known to a day, a month or a year.',
    'title' => 'An article needs a title in at least one language.',
    'title-length' => 'A title is at most :max characters.',
    'foreign-article' => 'Row :number is an article of another outlet. Reload the page.',
];
