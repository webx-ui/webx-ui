<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Contracts;

use WebxUi\Notifications\Templates\Html;

/**
 * A provider whose templates may be text or HTML, told apart by the template itself — a letter
 * (§6.2). For an HTML one the values are escaped for HTML with their line breaks kept, and the plain
 * text is read out of the message ({@see Html}); the provider's own
 * `escape()` and `plain()` are for a text one.
 */
interface WritesHtml
{
    public function isHtml(string $template): bool;
}
