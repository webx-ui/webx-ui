<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use WebxUi\Payments\Method;

/**
 * A type that can say which payment systems a method takes (groups spec §3.1). Optional: a type
 * without it shows no marks. The marks are the one declaration: «only Apple Pay» follows from them.
 */
interface ProvidesPaymentMarks
{
    /**
     * Keys of `PaymentMarks` the method offers the buyer right now, read from its settings.
     * Cheap and pure: no gateway call, no database.
     *
     * @return list<string>
     */
    public function marks(Method $method): array;
}
