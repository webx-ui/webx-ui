<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two settings of a form that became choices (§9, §5).
 *
 * How a submission is told about is now one way or the other: a form with channels chosen was set
 * up to use them, so it goes on with channels, and any other form with letters — what each of them
 * was doing before. Written down rather than left to be worked out on every read, so that a form
 * keeps its way when its channels are later taken off.
 *
 * Where the visitor goes after sending was a string; it is a link of the panel now, and the string
 * is its address, unchanged. Nothing else in the options is touched: the addresses of a form that
 * goes on with channels stay, for the day somebody switches back.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->each(static function (array $options): array {
            if (! in_array($options['notify_via'] ?? null, ['email', 'channels'], true)) {
                $channels = $options['notify_channels'] ?? null;
                $options['notify_via'] = is_array($channels) && $channels !== [] ? 'channels' : 'email';
            }

            $redirect = $options['redirect'] ?? null;

            if (is_string($redirect)) {
                $address = trim($redirect);

                if ($address === '') {
                    unset($options['redirect']);
                } else {
                    [$url, $hash] = array_pad(explode('#', $address, 2), 2, null);
                    $url = trim((string) $url);
                    $hash = $hash === null ? '' : trim($hash);

                    $options['redirect'] = [
                        'target' => 'url',
                        'entity_type' => null,
                        'entity_id' => null,
                        'url' => $url === '' ? null : $url,
                        'hash' => $hash === '' ? null : $hash,
                        'new_tab' => false,
                        'rel' => [],
                    ];
                }
            }

            return $options;
        });
    }

    public function down(): void
    {
        $this->each(static function (array $options): array {
            unset($options['notify_via']);

            $redirect = $options['redirect'] ?? null;

            if (is_array($redirect)) {
                // An address goes back to being one; a record of the site has no string to go
                // back to, and the form goes back to its thank-you.
                $url = ($redirect['target'] ?? null) === 'url' ? (string) ($redirect['url'] ?? '') : '';
                $hash = (string) ($redirect['hash'] ?? '');
                $address = $url.($hash === '' ? '' : '#'.$hash);

                if ($address === '') {
                    unset($options['redirect']);
                } else {
                    $options['redirect'] = $address;
                }
            }

            return $options;
        });
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function each(callable $change): void
    {
        foreach (DB::table('inbox_forms')->select(['id', 'options'])->orderBy('id')->get() as $row) {
            $options = is_string($row->options) ? json_decode($row->options, true) : null;
            $options = is_array($options) ? $options : [];
            $changed = $change($options);

            if ($changed !== $options) {
                DB::table('inbox_forms')->where('id', $row->id)->update([
                    'options' => json_encode($changed === [] ? new stdClass : $changed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }
};
