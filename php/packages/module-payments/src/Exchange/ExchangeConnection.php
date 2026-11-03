<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use Illuminate\Contracts\Config\Repository;

/**
 * The connection the journal writes through (decision 38): its own, so that an insert commits by
 * itself and a status change rolled back around it never takes the event along.
 *
 * Unless the site names one (`webx-payments.exchange.connection`), it is a copy of the default
 * connection under {@see self::COPY}, made the first time it is asked for: the same database, a
 * second session. An in-memory SQLite database is private to the PDO that opened it, so a second
 * session would see an empty one — there the default connection itself is used, and the journal
 * shares its transactions. That is a test database, never a site's.
 */
final class ExchangeConnection
{
    public const string COPY = 'webx-payments-exchange';

    private ?string $name = null;

    public function __construct(private readonly Repository $config) {}

    public function name(): string
    {
        return $this->name ??= $this->resolve();
    }

    private function resolve(): string
    {
        $configured = $this->config->get('webx-payments.exchange.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = (string) $this->config->get('database.default');
        $settings = $this->config->get("database.connections.{$default}");

        if (! is_array($settings) || (($settings['driver'] ?? null) === 'sqlite' && ($settings['database'] ?? null) === ':memory:')) {
            return $default;
        }

        $this->config->set('database.connections.'.self::COPY, $settings);

        return self::COPY;
    }
}
