<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Delivery\Models\DeliveryPoint;

/** The points as the base hands them to method types: copies of rows, never the models. */
final class Points
{
    public function ref(int $id): ?PointRef
    {
        $point = DeliveryPoint::query()->find($id);

        return $point === null ? null : PointRef::of($point);
    }
}
