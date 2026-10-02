<?php

declare(strict_types=1);

namespace WebxUi\Audit\Probes;

/**
 * Opens a TLS connection to the host and reads the certificate it presents.
 *
 * With the same SNI and the same `resolve_to` as every other probe, so behind a proxy it is the
 * certificate a visitor gets. A chain that does not verify is read a second time without
 * verification — a certificate that is there but untrusted is a finding, not a silence.
 * Bound in the container so tests replace it: nothing here can run without a network.
 */
class CertificateReader
{
    public function read(string $host, ?string $connectTo = null, int $port = 443): ?Certificate
    {
        if (! function_exists('openssl_x509_parse')) {
            return null;
        }

        $trusted = true;
        $certificate = $this->capture($host, $connectTo ?? $host, $port, true);

        if ($certificate === null) {
            $trusted = false;
            $certificate = $this->capture($host, $connectTo ?? $host, $port, false);
        }

        if ($certificate === null) {
            return null;
        }

        $info = openssl_x509_parse($certificate);

        if (! is_array($info) || ! isset($info['validTo_time_t'])) {
            return null;
        }

        $issuer = $info['issuer']['O'] ?? $info['issuer']['CN'] ?? null;

        return new Certificate(
            (int) $info['validTo_time_t'],
            self::covers($this->names($info), $host),
            $trusted,
            is_string($issuer) ? $issuer : null,
        );
    }

    /**
     * Whether one of a certificate's names covers the host; `*.shop.com` covers one label.
     *
     * @param  list<string>  $names
     */
    public static function covers(array $names, string $host): bool
    {
        $host = strtolower($host);

        foreach ($names as $name) {
            $name = strtolower($name);

            if ($name === $host) {
                return true;
            }

            if (str_starts_with($name, '*.')) {
                $suffix = substr($name, 1);
                $label = substr($host, 0, -strlen($suffix));

                if (str_ends_with($host, $suffix) && $label !== '' && ! str_contains($label, '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return mixed The peer certificate resource, or null. */
    private function capture(string $host, string $target, int $port, bool $verify): mixed
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => ! $verify,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ]]);

        $client = @stream_socket_client("ssl://{$target}:{$port}", $code, $message, 10, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        return $params['options']['ssl']['peer_certificate'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $info
     * @return list<string>
     */
    private function names(array $info): array
    {
        $names = [];
        $alternative = $info['extensions']['subjectAltName'] ?? '';

        foreach (explode(',', is_string($alternative) ? $alternative : '') as $entry) {
            $entry = trim($entry);

            if (str_starts_with($entry, 'DNS:')) {
                $names[] = substr($entry, 4);
            }
        }

        $common = $info['subject']['CN'] ?? null;

        if ($names === [] && is_string($common)) {
            $names[] = $common;
        }

        return $names;
    }
}
