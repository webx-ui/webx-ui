<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use WebxUi\Inbox\Models\Submission;

/**
 * What was around the submission: the page, the language, the campaign, the address.
 *
 * None of it is shown to the visitor and all of it is shown to whoever deals with the
 * submission — "which page was this sent from" is the first question asked of a form that sits
 * on nine of them.
 *
 * The address is here because the antispam needs one and because an abuse report needs one.
 * A site that would rather not keep it says so (`anonymise_ip`), and then the last octet is
 * dropped before it is written: enough to tell two visitors apart, not enough to tell which
 * flat they are in.
 */
final class Meta
{
    public function __construct(private readonly Config $config) {}

    /**
     * @param  string  $source  where the submission came from — see `Submission::SOURCE_*`
     * @return array<string, mixed>
     */
    public function of(Request $request, string $source = Submission::SOURCE_WEB): array
    {
        // A submission typed in by hand has no visitor: the address, the browser and the page
        // would be the administrator's own, and a card that showed them beside "how it
        // arrived" would be answering a question nobody asked with somebody else's facts.
        if ($source !== Submission::SOURCE_WEB) {
            return array_filter(['locale' => app()->getLocale()]);
        }

        // Where the visitor came from (FORM_STEPS §8), as the widgets' attribution.js posts it. A
        // site that would rather not know turns it off, and then none of it is written.
        $attribution = (bool) $this->config->get('webx-inbox.attribution', true);

        return array_filter([
            'ip' => $this->ip($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            // Where the form was: the referrer of a POST from a page is that page.
            'page' => $this->text($request->input('webx_page') ?? $request->headers->get('referer')),
            // Where the visitor came from before that, which only the browser can know: the
            // outside page of the last touch, and the page of the site they came in on.
            'referrer' => $attribution ? $this->short($request->input('webx_referrer')) : null,
            'landing' => $attribution ? $this->short($request->input('webx_landing')) : null,
            // The campaign of the last touch, and of the first — kept only with consent.
            'utm' => $attribution ? $this->utm($request->all()) : [],
            'utm_first' => $attribution ? $this->utm((array) $request->input('webx_utm_first', [])) : [],
            'locale' => app()->getLocale(),
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function ip(Request $request): ?string
    {
        $ip = $request->ip();

        if ($ip === null || ! $this->config->get('webx-inbox.anonymise_ip', false)) {
            return $ip;
        }

        if (str_contains($ip, ':')) {
            // IPv6: keep the network, drop the interface — the same trade as the last octet.
            $parts = array_slice(explode(':', $ip), 0, 4);

            return implode(':', $parts).'::';
        }

        $parts = explode('.', $ip);

        return count($parts) === 4 ? implode('.', [$parts[0], $parts[1], $parts[2], '0']) : $ip;
    }

    /**
     * Only `utm_*`, a key cut to 32 and a value to 255: whatever else a link carried — `gclid`,
     * a visitor's own parameter — is not the campaign, and a body is anybody's to write.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, string>
     */
    private function utm(array $input): array
    {
        $utm = [];

        foreach ($input as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'utm_') || ! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $utm[mb_substr($key, 0, 32)] = mb_substr((string) $value, 0, 255);
        }

        return $utm;
    }

    /** A referrer or a landing page: an address, cut as a value of the campaign is. */
    private function short(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 2000) : null;
    }
}
