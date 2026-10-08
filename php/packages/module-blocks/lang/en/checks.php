<?php

declare(strict_types=1);

return [
    'no-marker' => 'No data-wx-block on the root: the script will not run, and the panel cannot highlight the block in the preview.',
    'stray-selectors' => 'Selectors outside the block prefix .b-:slug: :selectors',
    'bare-selectors' => 'Element selectors reach the whole site: :selectors',
    'media-query' => '@media measures the window. A block is sized by its container: use @container.',
    'string-on-text' => 'With a shortcode in it, :field is HTML: a string function or a cast hands that HTML to {{ }}, which escapes it a second time. Change it through wx_text(): {{ wx_text(:field)->trimEnd(".") }} — trim, trimStart, stripPrefix, stripSuffix and map() keep it HTML.',
    'variables-missing' => 'The template uses :variables, which the schema does not declare. Publishing will be refused.',
    'ok-marker' => 'The root carries data-wx-block.',
    'ok-prefix' => 'Every selector starts with .b-:slug.',
    'ok-bare' => 'No bare element selectors.',
    'ok-container' => 'Width is decided by container queries.',
    'ok-variables' => 'Every variable of the template is a field of the schema.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Block values for fields the type does not have',
            'found' => 'Blocks hold values for fields their type does not define — left by an import or by a field taken out of the type.',
            'why' => 'Nothing of it reaches a visitor, but it is in the editor\'s data and in what an agent reads, and it turns up as a wrong line a block is recognised by.',
            'fix' => 'Remove them with the fix, or for the whole site with php artisan webx:blocks:prune. A block of a type that no longer exists is left alone. Repeater items are checked against the repeater\'s fields.',
        ],
        'unknown_shortcodes' => [
            'title' => 'Mistyped shortcodes',
            'found' => 'A text holds a bracket that is nearly a shortcode of this site, or one with arguments, and is not one.',
            'why' => 'Only registered shortcodes are replaced. Anything else is printed exactly as typed, brackets included, for every visitor to see.',
            'fix' => 'Correct the name to the one suggested, or write [[name]] if the page should show the brackets. The list is in the field\'s shortcode help and under «Settings» → «Shortcodes».',
        ],
        'hardcoded_values' => [
            'title' => 'Values typed instead of a shortcode',
            'found' => 'A text holds a phone number, an e-mail or another value that a shortcode from «Settings» → «Shortcodes» already holds.',
            'why' => 'It is right today and wrong the day the value changes: the shortcode changes everywhere, a value typed by hand only where somebody remembers it.',
            'fix' => 'Replace the value with the shortcode suggested, e.g. [phone]. A link to it is made by the shortcode too.',
        ],
    ],
    'syntax' => 'The template does not compile: :reason. Publishing will be refused.',
    'unknown-field-type' => 'Fields of a type this site does not know: :fields. The form draws a warning in their place and nothing checks their values.',
    'field-id' => 'Field ids must be letters, digits, _ and -, starting with a letter: :ids.',
    'marker-slug' => 'The root is marked data-wx-block=":marker", but the identifier is «:slug»: the script and the panel find the block by the exact identifier. Publishing will be refused.',
];
