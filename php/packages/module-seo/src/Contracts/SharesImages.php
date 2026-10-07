<?php

declare(strict_types=1);

namespace WebxUi\Seo\Contracts;

use WebxUi\Seo\Rendering\Images\SharedImage;

/**
 * The picture a social network is handed for an address: what kind of file it is, how big, and
 * whether it can be shared at all.
 *
 * Null means "not this one, try the next source": an SVG logo is no picture to a social network,
 * so a press outlet whose logo is one falls through to the site's default image rather than
 * printing a card with nothing in it. The default implementation reads the address alone; with
 * `webx-ui/module-media` installed the library's own record answers, with the dimensions and a
 * variant cut to the size networks recommend.
 */
interface SharesImages
{
    public function share(string $url): ?SharedImage;
}
