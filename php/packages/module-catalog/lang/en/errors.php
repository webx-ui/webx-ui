<?php

declare(strict_types=1);

return [
    'sku-taken' => 'The article number is taken by the product :name',
    'publish-needs-category' => 'A product cannot be published without a main category.',
    'category-has-products' => 'The category is not empty. Products in it: :count',
    'category-has-children' => 'The category has subcategories. Subcategories: :count',
    'slug-underscore' => 'The address of a category cannot contain "_": it marks a filter in the address.',
    'unknown-category' => 'There is no such category.',
    'move-into-itself' => 'A category cannot be moved inside itself.',
    'name-required' => 'Give it a name in at least one language.',
    'facet-shape' => 'A filter setting is a list of facet keys, each shown or hidden.',
    'images-mismatch' => 'The list has to name every picture of the product, and nothing else.',
    'image-unreachable' => 'The address did not answer with a file.',
    'image-not-a-picture' => 'The address answered with something that is not a JPEG, PNG, WebP or GIF picture.',
    'image-too-large' => 'The picture is larger than the limit.',
    'video-off' => 'Videos are switched off on this site.',
    'video-not-a-video' => 'This is not a video: an MP4 or WebM file, or a link to a video on YouTube.',
    'video-too-large' => 'The video is larger than :max MB.',
    'video-no-poster' => 'The cover of the video could not be fetched; check that the video exists and is public.',
    'dictionary-code' => 'A code is lowercase Latin letters, digits and single hyphens, at most :max characters.',
    'dictionary-code-taken' => 'This code is taken by another record of the list, perhaps one in the bin.',
    'dictionary-tone' => 'A tone is one of: :tones.',
];
