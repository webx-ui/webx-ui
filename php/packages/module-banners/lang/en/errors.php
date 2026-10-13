<?php

declare(strict_types=1);

return [
    'image-required' => 'A banner needs a picture.',
    'video-without-image' => 'A video needs a picture too: it is the poster, and what is seen where the video does not play.',
    'button-link' => 'Choose where the button leads.',
    'variant' => 'This look is not on the site’s list: :variants.',
    'buttons-max' => 'A banner has at most :max buttons.',
    'place-key' => 'A key is lowercase Latin letters, digits and dashes, starting with a letter, at most 64 characters.',
    'place-taken' => 'This key is taken.',
    'place-title' => 'A name in the default language is required.',
    'place-title-length' => 'A name is at most :max characters.',
    'place-not-empty' => 'Take the banners out of this place first, the bin included. Banners in it: :count.',
    'place-declared' => 'This place is declared in the configuration: the site’s templates ask for it by its key, so it cannot be renamed or deleted here.',
    'place-unknown' => 'There is no such place.',
    'ids' => 'The order has to be a list of ids.',
    'ids-foreign' => 'Only the banners of this place can be ordered here.',
    'layers-shape' => 'The layers must be a list.',
    'layers-max' => 'A banner has no more than :max layers.',
    'layer-type' => 'A layer is one of: :types.',
    'layer-tag' => 'A text layer is printed as one of: :tags.',
    'layer-text-long' => 'No more than :max characters in one language.',
    'layer-color' => 'A colour is #rrggbb or a colour of the theme.',
    'layer-range' => 'From :min to :max.',
    'layer-buttons-once' => 'The banner’s buttons are one layer — there is one already.',
];
