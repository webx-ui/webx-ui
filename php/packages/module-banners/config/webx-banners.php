<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Places
    |---------------------------------------------------------------------------
    |
    | Places a site's templates ask for by key: `banners('hero')`. A declared
    | place is in the panel from the first day, empty, and cannot be deleted or
    | renamed there — a template asks for it by this key. An administrator adds
    | places of their own in the panel.
    |
    | A title is a plain string or a translation key. `layout` and `options`
    | override the ones below for this place only.
    |
    */

    'places' => [
        'hero' => [
            'title' => 'webx-banners::places.hero',
            'layout' => 'slider',
        ],
        'promo' => [
            'title' => 'webx-banners::places.promo',
            'layout' => 'single',
            'options' => ['ratio' => '3/1', 'ratio_mobile' => '3/2'],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Layout
    |---------------------------------------------------------------------------
    |
    | The layout of a place that names none: single, random or slider. The
    | package draws none of them — `banners_layout()` hands the choice to the
    | site's template, which prints the markup.
    |
    | Not `layout`: at the top of a module's config that key is the Blade
    | layout its public pages stand in, and `webx:doctor` looks for it as a
    | component.
    |
    */

    'default_layout' => 'slider',

    /*
    |---------------------------------------------------------------------------
    | Options
    |---------------------------------------------------------------------------
    |
    | What a site's template reads through banners_layout(). A place overrides
    | any of these under its own `options`; a key the package does not know is
    | handed to the template as it is, so a site may add its own.
    |
    */

    'options' => [
        'interval' => 6000,          // ms between slides
        'autoplay' => true,          // off under prefers-reduced-motion regardless
        'loop' => true,              // after the last slide, the first
        'arrows' => true,
        'dots' => true,
        'pause_on_hover' => true,    // and on focus inside the slider
        'ratio' => '16/6',           // aspect-ratio of the frame on wide screens
        'ratio_mobile' => '4/5',     // below the breakpoint
        'breakpoint' => 768,         // px: below it image_mobile, ratio_mobile and no video
        'video_on_mobile' => false,  // below the breakpoint the poster rather than the video
    ],

    /*
    |---------------------------------------------------------------------------
    | Button variants
    |---------------------------------------------------------------------------
    |
    | How a button may look: key => name for the panel, a plain string or a
    | translation key. The key is what the site's template turns into a class.
    | The first one is the fallback: a button whose variant was taken out of
    | this list is printed with it rather than dropped.
    |
    */

    'variants' => [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'link' => 'Link',
    ],

];
