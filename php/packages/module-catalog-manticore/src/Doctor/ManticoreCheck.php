<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Catalog\Manticore\Manticore;
use WebxUi\Catalog\Manticore\ManticoreEngine;

/**
 * What the Manticore engine needs and nothing else announces (decisions 3 and 9 of the Manticore
 * spec): a prefix that is set and is nobody else's, a server that answers, and tables of the
 * schema the contributors write now. A table out of date is only reported — on a large catalogue
 * the rebuild is minutes of load, and its time is a person's to choose.
 *
 * Silent unless the site runs on `manticore`: a site on the database engine has nothing to fix here.
 */
final class ManticoreCheck implements Check
{
    private const SUBJECT = 'Manticore';

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly Manticore $server,
    ) {}

    public function run(): array
    {
        if ($this->config->get('webx-catalog.engine') !== 'manticore') {
            return [];
        }

        try {
            $prefix = $this->server->prefix();
        } catch (LogicException $missing) {
            return [Diagnosis::fail(self::SUBJECT, $missing->getMessage())];
        }

        try {
            $version = $this->version();
            $foreign = $this->foreign($prefix);
            /** @var ManticoreEngine $engine */
            $engine = $this->container->make(ManticoreEngine::class);
            $status = $engine->status();
        } catch (Throwable $failure) {
            return [Diagnosis::fail(self::SUBJECT, "{$this->server->address()} does not answer ({$failure->getMessage()}): lists, filters and the search fall back on the database or answer 503.")];
        }

        $diagnoses = [Diagnosis::ok(self::SUBJECT, "{$this->server->address()}, version {$version}, tables [{$prefix}_*].")];

        if ($foreign !== []) {
            $diagnoses[] = Diagnosis::warn(self::SUBJECT, "the prefix [{$prefix}] begins tables that are not this catalogue's: ".implode(', ', array_slice($foreign, 0, 5)).' — choose a prefix nobody else on the server starts with.');
        }

        foreach ($status as $locale => $table) {
            $diagnoses[] = match ($table['state']) {
                'missing' => Diagnosis::warn(self::SUBJECT, "there is no table for [{$locale}] — run `php artisan webx:catalog:index --rebuild`."),
                'stale' => Diagnosis::warn(self::SUBJECT, "the table of [{$locale}] is out of date: {$table['reason']} — run `php artisan webx:catalog:index --rebuild` when the load allows; the storefront works on the old one meanwhile."),
                default => Diagnosis::ok(self::SUBJECT, "[{$table['table']}]: {$table['documents']} products."),
            };
        }

        return $diagnoses;
    }

    private function version(): string
    {
        foreach ($this->server->sql('SHOW VERSION')[0]['data'] ?? [] as $row) {
            if (is_array($row) && ($row['Component'] ?? null) === 'Daemon') {
                return (string) ($row['Version'] ?? '?');
            }
        }

        return '?';
    }

    /**
     * Tables that start with the prefix and are not this catalogue's.
     *
     * @return list<string>
     */
    private function foreign(string $prefix): array
    {
        $own = $prefix.'_catalog_products_';
        $foreign = [];

        foreach ($this->server->sql('SHOW TABLES')[0]['data'] ?? [] as $row) {
            $name = is_array($row) ? (string) ($row['Table'] ?? '') : '';

            if (str_starts_with($name, $prefix) && ! str_starts_with($name, $own)) {
                $foreign[] = $name;
            }
        }

        return $foreign;
    }
}
