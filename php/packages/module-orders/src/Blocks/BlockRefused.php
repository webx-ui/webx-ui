<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use WebxUi\Orders\Refusal;

/**
 * A block of the snapshot no module registered, or one its owner does not accept (§6.4). A fault
 * of the code that passed it, not of the buyer: the errors are the owner's, as it wrote them.
 */
final class BlockRefused extends Refusal
{
    /** @param  list<string>  $errors */
    public function __construct(public readonly string $block, public readonly array $errors)
    {
        parent::__construct('block', "The block «{$block}» is refused: ".implode('; ', $errors));
    }
}
