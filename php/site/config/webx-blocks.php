<?php

/*
|--------------------------------------------------------------------------
| Block types — what this site says about them
|--------------------------------------------------------------------------
|
| Only the keys the site has an opinion on. Everything else comes from the
| package's own configuration underneath, which `php artisan vendor:publish
| --tag=webx-blocks-config` would copy here in full — and freeze at the
| version it was copied from.
|
| Nothing reads this file without webx-ui/module-blocks, so a site that did
| not choose the module can keep it or delete it.
|
*/

return [

    /*
    | The layout the block editor's stage stands in; `webx:panel --sync`
    | points it at `<x-layout>`.
    */

    'layout' => env('WEBX_BLOCKS_LAYOUT'),

    /*
    | The regions of `components/layout.blade.php`: the header and the footer
    | are trees of blocks edited in the panel ("Site regions"), and for as long
    | as one of them is empty or unpublished the site prints the view named in
    | the tag's `fallback` — `components/header.blade.php`, the one it always
    | printed. A region is declared here and printed there; one without the
    | other is either a panel section that changes nothing or a tag that only
    | ever prints its fallback, and `php artisan webx:doctor` says so.
    */

    'regions' => [
        'header' => ['title' => 'trans::webx-blocks::regions.header'],
        'footer' => ['title' => 'trans::webx-blocks::regions.footer'],
    ],

];
