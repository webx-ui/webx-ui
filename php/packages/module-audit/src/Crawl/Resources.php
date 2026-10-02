<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeResponse;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;
use WebxUi\Audit\Runs\AuditRun;

/**
 * Stage 5 (§3): what the pages load and where their external links lead, asked `HEAD` (with
 * `GET` when the server does not do `HEAD`), once per run however many pages share it, under a
 * limit of its own.
 *
 * Like the crawl, the queue is the table: `collect()` puts every address in `audit_resources`
 * once the crawl is done, `check()` asks the rows without `checked_at` a few at a time, and
 * `finish()` copies the answers onto the links.
 *
 * A stand's address is not asked — it is an error already (`hosts.dev_page`) and usually does not
 * answer from production at all. Somebody else's host is asked directly: `resolve_to` is where
 * this site lives, not where they do.
 */
final class Resources
{
    /** The order addresses take the limit in: what breaks a page first, external pages last. */
    private const PRIORITY = [AuditResource::OG, AuditResource::ICON, AuditResource::IMAGE, AuditResource::CSS, AuditResource::JS, AuditResource::OTHER, AuditResource::PAGE];

    /** `<link rel>` that loads something; canonical, alternate, preconnect and the like do not. */
    private const LOADING = ['stylesheet', 'icon', 'apple-touch-icon', 'preload', 'modulepreload', 'manifest'];

    /** What a browser that takes modern pictures says — so a server that negotiates can. */
    private const ACCEPT_IMAGES = 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8';

    private const PICTURE = '~\.(jpe?g|png|gif|webp|avif|svg|ico|bmp)(\?|$)~i';

    public function __construct(private readonly Config $config) {}

    /** Every address worth asking, once, up to the limit; the links learn which row is theirs. */
    public function collect(AuditRun $run): void
    {
        $limit = max(0, (int) $this->config->get('webx-audit.resources_limit', 2000));
        /** @var array<string, array{url: string, kind: string, host_class: string|null}> $found */
        $found = [];

        AuditLink::query()
            ->where('run_id', $run->id)
            ->where('host_class', '<>', HostClassifier::DEV)
            ->whereIn('kind', [AuditLink::A, AuditLink::IMG, AuditLink::SRCSET, AuditLink::SCRIPT, AuditLink::LINK, AuditLink::STYLE, AuditLink::META])
            ->select(['id', 'to_url', 'kind', 'rel', 'host_class'])
            ->lazyById(1000)
            ->each(function (AuditLink $link) use (&$found): void {
                $kind = self::kindOf($link);

                if ($kind === null) {
                    return;
                }

                $hash = sha1($link->to_url);

                // One address, the most telling kind: a picture that is also the OG picture is
                // the OG picture.
                if (! isset($found[$hash]) || self::rank($kind) < self::rank($found[$hash]['kind'])) {
                    $found[$hash] = ['url' => $link->to_url, 'kind' => $kind, 'host_class' => $link->host_class];
                }
            });

        uasort($found, static fn (array $a, array $b): int => self::rank($a['kind']) <=> self::rank($b['kind']));
        $kept = array_slice($found, 0, $limit, true);
        $now = Carbon::now();

        // A retried piece starts the stage over rather than doubling it.
        AuditResource::query()->where('run_id', $run->id)->delete();

        foreach (array_chunk($kept, 200, true) as $chunk) {
            $rows = [];

            foreach ($chunk as $hash => $resource) {
                $rows[] = [
                    'run_id' => $run->id,
                    'url' => mb_substr($resource['url'], 0, 2048),
                    'url_hash' => $hash,
                    'kind' => $resource['kind'],
                    'host_class' => $resource['host_class'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            AuditResource::query()->insert($rows);
        }

        $ids = AuditResource::query()->where('run_id', $run->id)->pluck('id', 'url_hash')->all();

        if ($ids === []) {
            return;
        }

        /** @var array<int, list<int>> $byResource */
        $byResource = [];

        AuditLink::query()
            ->where('run_id', $run->id)
            ->select(['id', 'to_url', 'kind', 'rel', 'host_class'])
            ->lazyById(1000)
            ->each(function (AuditLink $link) use ($ids, &$byResource): void {
                $id = $ids[sha1($link->to_url)] ?? null;

                if ($id !== null && self::kindOf($link) !== null) {
                    $byResource[(int) $id][] = $link->id;
                }
            });

        foreach ($byResource as $resource => $links) {
            foreach (array_chunk($links, 500) as $chunk) {
                AuditLink::query()->whereIn('id', $chunk)->update(['resource_id' => $resource]);
            }
        }
    }

    /** Asks the queue, a few at a time, until the deadline; true once nothing is left. */
    public function check(AuditRun $run, SiteClient $client, float $deadline): bool
    {
        $concurrency = max(1, (int) $this->config->get('webx-audit.concurrency', 2));
        $direct = $client->direct();

        do {
            /** @var list<AuditResource> $resources */
            $resources = AuditResource::query()
                ->where('run_id', $run->id)
                ->whereNull('checked_at')
                ->orderBy('id')
                ->limit($concurrency)
                ->get()
                ->all();

            if ($resources === []) {
                return true;
            }

            $own = [];
            $external = [];

            foreach ($resources as $resource) {
                $request = [self::method($resource), $resource->url, self::headers($resource)];

                if ($resource->host_class === HostClassifier::EXTERNAL) {
                    $external[(string) $resource->id] = $request;
                } else {
                    $own[(string) $resource->id] = $request;
                }
            }

            $answers = $client->many($own) + $direct->many($external);

            foreach ($resources as $resource) {
                $answer = $answers[(string) $resource->id] ?? new ProbeResponse($resource->url, null, error: 'No answer.');
                $method = self::method($resource);

                // A server that does not do HEAD says so with 405 or 501 — then it is asked properly.
                if ($method === 'HEAD' && in_array($answer->status, [405, 501], true)) {
                    $method = 'GET';
                    $answer = ($resource->host_class === HostClassifier::EXTERNAL ? $direct : $client)->get($resource->url);
                }

                $this->store($resource, $answer, $method);
            }
        } while (microtime(true) < $deadline);

        return false;
    }

    /** The answers onto the links: `audit_links.status` is what the address answered. */
    public function finish(AuditRun $run): void
    {
        $connection = AuditLink::query()->getConnection();
        $prefix = $connection instanceof Connection ? $connection->getTablePrefix() : '';
        $resources = $prefix.(new AuditResource)->getTable();
        $links = $prefix.(new AuditLink)->getTable();

        AuditLink::query()->where('run_id', $run->id)->whereNotNull('resource_id')->update([
            'status' => DB::raw("(select r.status from {$resources} r where r.id = {$links}.resource_id)"),
        ]);
    }

    private function store(AuditResource $resource, ProbeResponse $answer, string $method): void
    {
        $length = $answer->header('content-length');
        $size = null;

        if ($resource->kind === AuditResource::OG && $answer->ok() && $answer->body !== '') {
            $size = @getimagesizefromstring($answer->body) ?: null;
        }

        $control = $answer->header('cache-control');
        $encoding = $answer->header('x-encoded-content-encoding') ?? $answer->header('content-encoding');

        $resource->update([
            'checked_at' => Carbon::now(),
            'method' => $method,
            'status' => $answer->status,
            'error' => $answer->error === null ? null : mb_substr($answer->error, 0, 255),
            'location' => $answer->redirect() ? mb_substr((string) $answer->location(), 0, 2048) : null,
            'content_type' => $answer->header('content-type') === null ? null : mb_substr((string) $answer->header('content-type'), 0, 128),
            'bytes' => is_numeric($length) ? (int) $length : ($method === 'GET' && $answer->ok() ? strlen($answer->body) : null),
            'cache_control' => $control === null ? null : mb_substr($control, 0, 255),
            'compression' => $encoding === null || trim($encoding) === '' ? null : mb_substr(strtolower(trim($encoding)), 0, 16),
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
            'total_ms' => $answer->ms,
        ]);
    }

    /** What a link loads or leads to, as a row of this stage — or null for nothing to ask. */
    public static function kindOf(AuditLink $link): ?string
    {
        $path = (string) parse_url($link->to_url, PHP_URL_PATH);

        return match ($link->kind) {
            AuditLink::A => $link->host_class === HostClassifier::EXTERNAL ? AuditResource::PAGE : null,
            AuditLink::IMG, AuditLink::SRCSET => AuditResource::IMAGE,
            AuditLink::SCRIPT => AuditResource::JS,
            AuditLink::META => in_array($link->rel, ['og:image', 'twitter:image'], true) ? AuditResource::OG : null,
            AuditLink::STYLE => preg_match(self::PICTURE, $path) === 1 ? AuditResource::IMAGE : AuditResource::OTHER,
            AuditLink::LINK => self::linkKind($link->rel, $path),
            default => null,
        };
    }

    private static function linkKind(?string $rel, string $path): ?string
    {
        $tokens = preg_split('/\s+/', strtolower((string) $rel)) ?: [];

        if (array_intersect($tokens, self::LOADING) === []) {
            return null;
        }

        return match (true) {
            in_array('stylesheet', $tokens, true) => AuditResource::CSS,
            in_array('icon', $tokens, true), in_array('apple-touch-icon', $tokens, true) => AuditResource::ICON,
            preg_match('~\.css(\?|$)~i', $path) === 1 => AuditResource::CSS,
            preg_match('~\.m?js(\?|$)~i', $path) === 1 => AuditResource::JS,
            preg_match(self::PICTURE, $path) === 1 => AuditResource::IMAGE,
            default => AuditResource::OTHER,
        };
    }

    private static function rank(string $kind): int
    {
        $index = array_search($kind, self::PRIORITY, true);

        return is_int($index) ? $index : count(self::PRIORITY);
    }

    /** The OG picture is downloaded — its size is the check; everything else is `HEAD`. */
    private static function method(AuditResource $resource): string
    {
        return $resource->kind === AuditResource::OG ? 'GET' : 'HEAD';
    }

    /**
     * @return array<string, string>
     */
    private static function headers(AuditResource $resource): array
    {
        return in_array($resource->kind, AuditResource::PICTURES, true) ? ['Accept' => self::ACCEPT_IMAGES] : [];
    }
}
