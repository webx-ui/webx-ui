<?php

declare(strict_types=1);

return [
    'schema' => [
        'invalid' => 'The schema is not a JSON object.',
        'version' => 'Unknown schema version: this module reads version 1.',
    ],
    'currency' => [
        'invalid' => 'The currency is a three-letter ISO 4217 code in capitals, like UAH or EUR.',
    ],
    'pricing' => [
        'round' => 'The rounding step is a number above 0.',
    ],
    'discount' => [
        'percent' => 'The discount is a percent above 0 and below 100.',
        'until' => 'The last day of the discount is a date YYYY-MM-DD.',
    ],
    'steps' => [
        'empty' => 'A configurator needs at least one step.',
    ],
    'key' => [
        'invalid' => 'The key «:key» is not a-z, 0-9 and _, starting with a letter.',
        'duplicate' => 'The key «:key» is taken: steps and size fields share keys.',
    ],
    'type' => [
        'unknown' => 'Unknown step type: cards, swatches, buttons or dimensions.',
    ],
    'options' => [
        'empty' => 'A choice step needs at least one option.',
    ],
    'fields' => [
        'empty' => 'A sizes step needs at least one field.',
    ],
    'value' => [
        'invalid' => 'The value «:value» is not a slug: a-z, 0-9, - and _.',
        'duplicate' => 'The value «:value» is used twice in this step.',
    ],
    'label' => [
        'missing' => 'A name in the default language (:locale) is needed.',
    ],
    'text' => [
        'invalid' => 'A text is a string or a set of languages.',
    ],
    'image' => [
        'missing' => 'Choose a picture from the media library.',
        'unknown' => 'The media library has no file «:key».',
    ],
    'product' => [
        'invalid' => 'A product is named by its article number (SKU), up to 64 characters.',
        'unknown' => 'The catalogue has no product with the article number «:sku».',
    ],
    'swatch' => [
        'invalid' => 'A swatch is #rgb, #rrggbb or a gradient of hex colours.',
    ],
    'price' => [
        'invalid' => 'A price is a number.',
        'per_m2' => 'A price per m² needs an area: give the options of one step an area formula.',
    ],
    'area' => [
        'main_count' => 'Options of only one step may have an area formula.',
        'missing' => 'Every option of the main step needs an area formula.',
        'syntax' => 'The formula is wrong at character :position: :reason.',
        'unknown_key' => 'The formula uses «:key», which is not a size field.',
    ],
    'range' => [
        'invalid' => 'The range is wrong: min above max, or a step not above 0.',
    ],
    'limits' => [
        'unknown_field' => 'Limits name «:key», which is not a size field.',
    ],
    'defaults' => [
        'unknown_field' => 'Defaults name «:key», which is not a size field.',
    ],
    'condition' => [
        'invalid' => 'A condition is a step and the values it is chosen with.',
        'unknown_step' => 'There is no step «:step».',
        'forward' => 'The step «:step» does not stand above this one: a condition can only look up.',
        'not_choice' => 'The step «:step» is a sizes step: a condition names a step of options.',
        'unknown_value' => 'The step «:step» has no option «:value».',
    ],
    'initial' => [
        'unknown' => 'The initial choice «:key» is not a step, a field or one of its values.',
    ],
    'previews' => [
        'condition' => 'A picture rule names a step or a value that does not exist.',
    ],
    'values' => [
        'range' => 'The size «:key» is from :min to :max.',
        'number' => 'The size «:key» is a number.',
    ],
];
