<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

/**
 * Variables nobody knows in advance — `property.<code>`, one per property. Asked every time the
 * variables are listed, so a property added a minute ago is a variable now.
 */
interface ProductVariableProvider
{
    /**
     * @return iterable<ProductVariable>
     */
    public function variables(): iterable;
}
