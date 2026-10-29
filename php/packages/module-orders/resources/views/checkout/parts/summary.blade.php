{{--
    The node `checkout.summary` of the composition: the column of the totals (`checkout.summary`,
    which `GET /checkout/summary` prints alone too) with the variables of `$summary`.
--}}
@include('webx-orders::checkout.summary', $summary)
