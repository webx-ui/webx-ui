<?php

declare(strict_types=1);

namespace WebxUi\Audit\Probes;

/**
 * One answer of the site to one probe, redirects not followed: every step of a chain is its own
 * answer (§3). `status` is null when nothing answered at all — no DNS, refused, timed out — and
 * `error` then says which.
 */
final readonly class ProbeResponse
{
    /**
     * @param  array<string, string>  $headers  Lowercase names, the first value of each.
     */
    public function __construct(
        public string $url,
        public ?int $status,
        public array $headers = [],
        public string $body = '',
        public int $ms = 0,
        public ?string $error = null,
    ) {}

    public function ok(): bool
    {
        return $this->status !== null && $this->status >= 200 && $this->status < 300;
    }

    public function redirect(): bool
    {
        return $this->status !== null && $this->status >= 300 && $this->status < 400 && $this->location() !== null;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Where a redirect leads, made absolute against the address that answered. */
    public function location(): ?string
    {
        $location = $this->header('location');

        if ($location === null || $location === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($this->url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$location;
        }

        return $origin.(str_starts_with($location, '/') ? $location : '/'.$location);
    }

    /**
     * The answer as the run keeps it: no body.
     *
     * @return array{url: string, status: int|null, location: string|null, ms: int, error: string|null}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'status' => $this->status,
            'location' => $this->location(),
            'ms' => $this->ms,
            'error' => $this->error,
        ];
    }
}
