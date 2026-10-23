<?php

declare(strict_types=1);

use WebxUi\Compositions\Compositions;

if (! function_exists('composition_view')) {
    /**
     * The compiled view of a composition for a handle — what a dispatcher includes:
     * `@include(composition_view('layout', $attributes->get('handle')))`.
     */
    function composition_view(string $composition, ?string $handle = null): string
    {
        return app(Compositions::class)->view($composition, $handle);
    }
}
