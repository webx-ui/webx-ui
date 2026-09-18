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

        return array_filter([
            'ip' => $this->ip($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            // Where the form was: the referrer of a POST from a page is that page.
            'page' => $this->text($request->input('webx_page') ?? $request->headers->get('referer')),
            // Where the visitor came from before that, which only the site can know — it
            // remembers the first referrer and posts it in a hidden field.
            'referrer' => $this->text($request->input('webx_referrer')),
            'utm' => $this->utm($request),
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
     * @return array<string, string>
     */
    private function utm(Request $request): array
    {
        $utm = [];

        foreach ($request->all() as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'utm_') || ! is_scalar($value)) {
                continue;
            }

            $utm[mb_substr($key, 0, 32)] = mb_substr((string) $value, 0, 255);
        }

        return $utm;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 2000) : null;
    }
}
