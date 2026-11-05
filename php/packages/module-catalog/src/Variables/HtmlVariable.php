<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use WebxUi\Templates\Variable;

/** A variable whose value is markup, not a line of text: the third type of §4.4, beside text and list. */
final readonly class HtmlVariable extends Variable {}
