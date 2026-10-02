<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\SiteClient;
use XMLReader;

/**
 * The sitemap as a search engine reads it (§5.3): the files `robots.txt` names, or
 * `/sitemap.xml` when it names none, an index followed into its files — each file with what it
 * answered, whether it parses, how many addresses it holds and how its `lastmod` looks.
 *
 * The probes stage reads it for the checks of the files themselves, the crawl reads it again for
 * the addresses: a quick run has no crawl, and a list of fifty thousand addresses is not
 * something to keep in the run between the two.
 */
final class SitemapReader
{
    /** Sitemap files read at most — an index of indexes is followed, not explored forever. */
    public const FILES = 50;

    /** The protocol's limits for one file. */
    public const URLS = 50_000;

    public const BYTES = 50 * 1024 * 1024;

    /** Distinct `lastmod` values remembered — enough to tell "all the same" from "varied". */
    private const DATES = 100;

    /**
     * @param  list<string>  $declared  The `Sitemap:` lines of robots.txt.
     */
    public function read(string $baseUrl, array $declared, SiteClient $client, HostClassifier $hosts): SitemapSet
    {
        $base = rtrim($baseUrl, '/');
        $queue = [];

        foreach ($declared as $address) {
            $url = Urls::normalise($address);

            // Somebody else's sitemap is not read: its addresses are not the site's.
            if ($url !== null && $hosts->classify($url) === HostClassifier::OWN) {
                $queue[$url] = true;
            }
        }

        $fallback = Urls::normalise($base.'/sitemap.xml');

        // The conventional address is asked as well; when robots.txt names other files, its
        // absence is not a problem.
        if ($fallback !== null && ! isset($queue[$fallback])) {
            $queue[$fallback] = $queue === [] ? true : false;
        }

        $files = [];
        $urls = [];

        while ($queue !== [] && count($files) < self::FILES) {
            $file = (string) array_key_first($queue);
            $required = $queue[$file];
            unset($queue[$file]);

            $answer = $client->get($file, self::BYTES + 1);
            $entry = ['url' => $file, 'required' => $required, 'status' => $answer->status, 'error' => $answer->error, 'kind' => null, 'urls' => 0, 'bytes' => 0, 'lastmod' => ['count' => 0, 'future' => 0, 'distinct' => 0, 'first' => null]];

            if (! $answer->ok()) {
                $files[] = $entry;

                continue;
            }

            $xml = str_starts_with($answer->body, "\x1f\x8b") ? (string) @gzdecode($answer->body) : $answer->body;
            $entry['bytes'] = strlen($xml);
            $parsed = self::parse(substr($xml, 0, self::BYTES));
            $entry = [...$entry, 'kind' => $parsed['kind'], 'error' => $parsed['error'], 'urls' => count($parsed['locations']), 'lastmod' => $parsed['lastmod']];
            $files[] = $entry;

            foreach ($parsed['locations'] as $location) {
                if ($hosts->classify($location) !== HostClassifier::OWN) {
                    continue;
                }

                if ($parsed['kind'] === 'index') {
                    if (! isset($queue[$location]) && ! in_array($location, array_column($files, 'url'), true)) {
                        // A file the index names is as required as the index.
                        $queue[$location] = true;
                    }
                } else {
                    $urls[$location] = true;
                }
            }
        }

        return new SitemapSet($declared, $files, array_keys($urls));
    }

    /**
     * Every `<loc>` of a `urlset` or a `sitemapindex`, normalised, and how the `lastmod` look.
     *
     * @return array{kind: string|null, error: string|null, locations: list<string>, lastmod: array{count: int, future: int, distinct: int, first: string|null}}
     */
    public static function parse(string $xml): array
    {
        $result = ['kind' => null, 'error' => null, 'locations' => [], 'lastmod' => ['count' => 0, 'future' => 0, 'distinct' => 0, 'first' => null]];

        if (trim($xml) === '') {
            return [...$result, 'error' => 'The file is empty.'];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader;
        $locations = [];
        $dates = [];
        $count = 0;
        $future = 0;
        $first = null;
        $tomorrow = Carbon::now()->addDay();

        try {
            $reader->XML($xml, null, LIBXML_NONET);

            while (@$reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                $name = $reader->localName;

                if ($result['kind'] === null) {
                    $result['kind'] = match ($name) {
                        'urlset' => 'urlset',
                        'sitemapindex' => 'index',
                        default => null,
                    };

                    if ($result['kind'] === null) {
                        $result['error'] = 'The root element is <'.$name.'>, not <urlset> or <sitemapindex>.';

                        break;
                    }

                    continue;
                }

                if ($name === 'loc') {
                    $url = Urls::normalise(trim($reader->readString()));

                    if ($url !== null) {
                        $locations[$url] = true;
                    }
                } elseif ($name === 'lastmod') {
                    $value = trim($reader->readString());
                    $count++;
                    $first ??= $value;

                    if (count($dates) < self::DATES) {
                        $dates[$value] = true;
                    }

                    try {
                        if (Carbon::parse($value)->greaterThan($tomorrow)) {
                            $future++;
                        }
                    } catch (Throwable) {
                        // An unreadable date is the validator's business, not this check's.
                    }
                }
            }

            $error = libxml_get_last_error();

            if ($error !== false && $result['error'] === null) {
                $result['error'] = trim($error->message).' (line '.$error->line.')';
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $result['locations'] = array_keys($locations);
        $result['lastmod'] = ['count' => $count, 'future' => $future, 'distinct' => count($dates), 'first' => $first];

        return $result;
    }
}
