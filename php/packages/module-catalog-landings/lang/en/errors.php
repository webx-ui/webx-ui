<?php

declare(strict_types=1);

return [
    'slug-underscore' => 'A landing’s address cannot hold “_”: it marks the filter in an address.',
    'set-taken' => 'This set is already the landing “:name” on this base.',
    'set-empty' => 'Choose at least one value of a facet.',
    'category-facet' => 'The category is the landing’s base, not a part of its set.',
    'unknown-facet' => 'There is no facet “:key”.',
    'unknown-sort' => 'There is no sort “:sort”.',
    'unknown-product' => 'There is no product #:id.',
    'generate-facet' => 'Landings are generated over a facet of values: “:key” is not one.',
    'generate-template' => 'The address template needs {value}: otherwise every landing gets one address.',
    'generate-too-many' => 'One generation makes at most :max landings — narrow the bases or the values.',
];
