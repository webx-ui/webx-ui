<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** What a method asks the buyer once it is chosen (§6.1). */
enum Asks: string
{
    case Nothing = 'nothing';
    case Address = 'address';
    case Point = 'point';
}
