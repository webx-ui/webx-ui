<?php

declare(strict_types=1);

namespace WebxUi\Audit\Probes;

/**
 * What the probes stage saw: the answers by name, the certificate, and the inner address the
 * address-shape probes were made on. Empty for the stages after it — the bodies are not stored,
 * and the checks that read them run in the stage that took them.
 */
final class ProbeSet
{
    /**
     * @param  array<string, ProbeResponse>  $answers
     * @param  list<string>  $assets  The page's own CSS, JS and pictures that were asked.
     */
    public function __construct(
        private array $answers = [],
        public readonly ?Certificate $certificate = null,
        public readonly ?string $innerPath = null,
        public readonly array $assets = [],
    ) {}

    public function get(string $key): ?ProbeResponse
    {
        return $this->answers[$key] ?? null;
    }

    /** @return array<string, ProbeResponse> */
    public function all(): array
    {
        return $this->answers;
    }

    /**
     * The answers whose name starts with `$prefix` — `asset:`, `index:`.
     *
     * @return array<string, ProbeResponse>
     */
    public function prefixed(string $prefix): array
    {
        return array_filter($this->answers, static fn (string $key): bool => str_starts_with($key, $prefix), ARRAY_FILTER_USE_KEY);
    }

    public function empty(): bool
    {
        return $this->answers === [];
    }

    /**
     * As the run keeps it, without the bodies.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'answers' => array_map(static fn (ProbeResponse $answer): array => $answer->toArray(), $this->answers),
            'certificate' => $this->certificate?->toArray(),
            'inner_path' => $this->innerPath,
        ];
    }
}
