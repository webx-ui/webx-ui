<?php

declare(strict_types=1);

use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Shortcodes\ShortcodeText;

if (! function_exists('wx_text')) {
    /**
     * An editor's text field as one kind of value, whatever arrived: `{{ wx_text($heading)->trimEnd('.') }}`.
     *
     * A block template is handed a text field as a plain string when it holds no shortcode and as
     * a {@see ShortcodeText} when it does. Both print right with `{{ }}`, but a string function
     * turns the second into its HTML and `{{ }}` escapes that again — `Call &lt;a href=…`. This
     * makes either a ShortcodeText, whose methods change the text and stay HTML.
     */
    function wx_text(string|Stringable|null $value): ShortcodeText
    {
        return app(Shortcodes::class)->wrap($value);
    }
}
