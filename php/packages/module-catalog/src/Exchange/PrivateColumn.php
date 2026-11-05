<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use WebxUi\Catalog\Variables\ProductVariables;

/**
 * A column whose data stays between the panel and its file: it never becomes a variable of
 * {@see ProductVariables}, so no template can send it to a marketplace
 * (§13 of the feeds spec).
 */
interface PrivateColumn {}
