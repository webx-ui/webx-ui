<?php

declare(strict_types=1);

return [
    'type' => 'A property is one of: :types.',
    'type-fixed' => 'The type of a property does not change once saved: make a new property and move the values.',
    'code' => 'Latin letters, digits and single hyphens, at most :max characters.',
    'code-taken' => 'This code is taken in this language by: :holder.',
    'slug' => 'Latin letters, digits and single hyphens.',
    'slug-taken' => 'Another value of this property has this slug in this language.',
    'color' => 'A colour is #rrggbb.',
    'group' => 'There is no such group.',
    'group-in-use' => 'The group holds properties. Properties: :count',
    'unknown-property' => 'There is no such property.',
    'inherited' => '«:property» is already in the set of «:category» above; a category inherits it.',
    'outside-set' => 'This property is not in the set of the product\'s main category.',
    'number' => 'A number is expected.',
    'text' => 'A text is expected, or a map of languages.',
    'one-value' => 'This property takes one value.',
    'unknown-value' => 'There is no such value of this property.',
    'unknown-slug' => '«:property» has no value «:slug». Make it first with catalog_property_values_create, or give the id.',
    'leaves-only' => 'Only a value with nothing under it can be chosen here.',
    'value-in-use' => 'Products hold this value — merge it into another instead. Products: :count',
    'values-select' => 'Only a reference book has values.',
    'not-tree' => 'The values of this property are not a tree.',
    'move-into-itself' => 'A value cannot go under itself.',
    'merge-other' => 'A value merges into another value of the same property.',
    'merge-descendant' => 'A value cannot merge into one under it.',
    'intervals-number' => 'Only a number has intervals.',
    'interval-ends' => 'The upper end must be above the lower one.',
];
