<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Checkout\CheckoutContexts;

/**
 * The checkout as a browser goes through it: the cart's cookie on every request, and the
 * confirmation sent with what the column printed — the key of a repeat and the mark of time —
 * a few seconds after it was drawn, as a person presses the button (spec §24, §28).
 */
trait VisitsCheckout
{
    /**
     * @param  array<string, mixed>|null  $post  null — a GET
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    protected function visit(AddResult $cart, string $uri, ?array $post = null, bool $json = false, array $headers = []): TestResponse
    {
        // The headers of one request are not the next one's, nor its count of the cart: Testbench keeps
        // one container a test, and the route's controller holds its scoped services between requests.
        $this->flushHeaders();
        $this->app->make(CheckoutContexts::class)->forget();
        $request = $this->withCookie('webx_cart', (string) ($cart->token ?? ''))->withHeaders($headers);

        if ($json) {
            $request = $request->withHeader('Accept', 'application/json');
        }

        // The middleware of the web group checks the token; the tests of the forms are not about it.
        $request = $request->withoutMiddleware(ValidateCsrfToken::class);

        return $post === null ? $request->get($uri) : $request->post($uri, $post);
    }

    /**
     * The confirmation with the hidden fields of the column printed now; `$post` over them.
     *
     * @param  array<string, mixed>  $post
     * @return TestResponse<Response>
     */
    protected function submit(AddResult $cart, array $post = [], bool $json = false, string $prefix = ''): TestResponse
    {
        $fields = $this->hidden($cart, $prefix);
        $this->travel(5)->seconds();

        return $this->visit($cart, $prefix.'/checkout/place', [...$fields, ...$post], $json);
    }

    /**
     * The hidden fields of the confirmation as the column prints them.
     *
     * @return array<string, string>
     */
    protected function hidden(AddResult $cart, string $prefix = ''): array
    {
        $html = (string) $this->visit($cart, $prefix.'/checkout/summary')->assertOk()->getContent();
        $fields = [];

        foreach (['expected_payable', 'place_key', 'webx_ts'] as $name) {
            $this->assertMatchesRegularExpression('/name="'.$name.'" value="([^"]*)"/', $html);
            preg_match('/name="'.$name.'" value="([^"]*)"/', $html, $match);
            $fields[$name] = html_entity_decode($match[1]);
        }

        return $fields;
    }
}
