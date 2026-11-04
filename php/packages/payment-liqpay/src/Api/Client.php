<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Api;

use Illuminate\Http\Client\ConnectionException;
use WebxUi\Payment\LiqPay\Keys;
use WebxUi\Payment\LiqPay\LiqPayGateway;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayUnavailable;

/**
 * The thin client of LiqPay (spec §4): the SDK is one class on SHA1 and `version: 3`. Every request
 * goes through `GatewayHttp`, so the base journals it.
 */
final readonly class Client
{
    public function __construct(
        private GatewayHttp $http,
        private Keys $keys,
        private string $url,
        private int $version,
    ) {}

    public static function for(GatewayHttp $http, Keys $keys): self
    {
        $config = config('webx-payment-liqpay');

        return new self($http, $keys, (string) $config['api_url'], (int) $config['version']);
    }

    /**
     * The two fields of a request or of the form of the page. The first three fields lead `data`
     * in this order, as the recorded requests have them.
     *
     * @param  array<string, scalar>  $fields
     * @return array{data: string, signature: string}
     *
     * @throws GatewayUnavailable a key of the mode is missing
     */
    public function form(string $action, array $fields): array
    {
        $private = $this->keys->privateKey();

        if ($this->keys->publicKey === null || $private === null) {
            throw GatewayUnavailable::because(LiqPayGateway::word('connection.no_keys'));
        }

        $json = json_encode(
            ['version' => $this->version, 'public_key' => $this->keys->publicKey, 'action' => $action, ...$fields],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $data = base64_encode($json);

        return ['data' => $data, 'signature' => Signature::sign($data, $private, $this->version)];
    }

    /**
     * @param  array<string, scalar>  $fields
     *
     * @throws GatewayUnavailable no answer, a 5xx, or a body that is not JSON
     */
    public function call(string $action, array $fields): Answer
    {
        $form = $this->form($action, $fields);

        try {
            $response = $this->http->request()->asForm()->post($this->url, $form);
        } catch (ConnectionException $e) {
            throw GatewayUnavailable::timeout($e);
        }

        if (! $response->successful()) {
            throw GatewayUnavailable::answered($response->status());
        }

        return Answer::of($response->body());
    }
}
