<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Filesystem\Filesystem;
use Throwable;
use WebxUi\Compositions\Compositions;

/**
 * The text of a layout, as the checks that read it for `@stack('head')` and the regions need it.
 *
 * theme-default's `components/layout.blade.php` is a dispatcher of the composition `layout`
 * (COMPOSITIONS §6.1): one `@include(composition_view('layout', …))`, and the seams are in the
 * compiled view. Read as it is, it has none of them, and the site is told its layout drops every
 * metatag. A dispatcher is read as its compiled `default`; a layout written by hand, as itself.
 */
final class LayoutSource
{
    public static function of(string $contents, Views $views, Filesystem $files): string
    {
        if (preg_match('/composition_view\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $match) !== 1 || ! class_exists(Compositions::class)) {
            return $contents;
        }

        try {
            return $files->get($views->getFinder()->find(Container::getInstance()->make(Compositions::class)->view($match[1])));
        } catch (Throwable) {
            // A composition that does not compile is the compile step's to report, not this one's.
            return $contents;
        }
    }
}
