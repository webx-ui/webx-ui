<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Probes\ProbeResponse;

/**
 * `http://` is not one 301 to `https://` of the same host. Through a chain it still gets there
 * and is a warning; answering on plain http, or leading anywhere else, is an error.
 */
final class Https extends Check
{
    protected const ID = 'host.https';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::ERROR;

    private const STEPS = 5;

    public function run(AuditContext $context): iterable
    {
        $http = $context->probes->get('http');

        if ($http === null || $http->status === null) {
            return;
        }

        if ($http->ok()) {
            yield $this->found('http-answers', [], $http->url);

            return;
        }

        $target = $context->base().'/';
        $steps = [$http];
        $answer = $http;

        while ($answer->redirect() && count($steps) <= self::STEPS && rtrim((string) $answer->location(), '/') !== rtrim($target, '/')) {
            $answer = $context->client->get((string) $answer->location());
            $steps[] = $answer;
        }

        $last = $steps[count($steps) - 1];
        $arrived = rtrim((string) ($last->redirect() ? $last->location() : $last->url), '/') === rtrim($target, '/');

        if (! $arrived) {
            yield $this->found('http-elsewhere', ['location' => (string) $http->location()], $http->url, table: $this->table($steps));
        } elseif (count($steps) > 1 || ! in_array($http->status, [301, 308], true)) {
            yield $this->found('http-chain', ['steps' => count($steps)], $http->url, Severity::WARNING, $this->table($steps));
        }
    }

    /**
     * @param  list<ProbeResponse>  $steps
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}
     */
    private function table(array $steps): array
    {
        return [
            'columns' => [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('location', 'url')],
            'rows' => array_map(static fn (ProbeResponse $step): array => [
                'url' => $step->url,
                'status' => $step->status,
                'location' => $step->location(),
            ], $steps),
        ];
    }
}
