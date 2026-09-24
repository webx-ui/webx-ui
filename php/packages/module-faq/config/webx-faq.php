<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | FAQPage markup
    |---------------------------------------------------------------------------
    |
    | A FAQ block can put one FAQPage in the page's <head> for the questions it
    | shows — by default when the block shows every category, off when it picks
    | some, and the editor may turn it either way on the block. It needs
    | `webx-ui/module-seo`, which prints the <head>.
    |
    | Off here takes the switch away from every block: for a site whose own
    | layout already prints the markup, or that wants none.
    |
    */

    'markup' => (bool) env('WEBX_FAQ_MARKUP', true),

];
