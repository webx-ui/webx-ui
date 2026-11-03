<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/**
 * What a binding of a carrier's locality is after matching: the columns `locality_id`, `status`,
 * `method`, `reason` of the binding row (§5), and the candidates the queue shows a person.
 *
 * The same object goes back in as the current binding on the next run: a matched one with a
 * locality, and an ignored one, are kept as they are (§4.3, step 1).
 */
final readonly class MatchResult
{
    /**
     * @param  list<Candidate>  $candidates
     */
    public function __construct(
        public BindingStatus $status,
        public ?int $localityId = null,
        public ?BindingMethod $method = null,
        public ?QueueReason $reason = null,
        public array $candidates = [],
        /** True when the matcher left a current binding untouched (step 1). */
        public bool $kept = false,
    ) {}

    public static function matched(int $localityId, BindingMethod $method): self
    {
        return new self(BindingStatus::Matched, $localityId, $method);
    }

    /** @param  list<Candidate>  $candidates */
    public static function pending(QueueReason $reason, array $candidates = []): self
    {
        return new self(BindingStatus::Pending, reason: $reason, candidates: $candidates);
    }

    /** "Do not bind" from the queue. */
    public static function ignored(): self
    {
        return new self(BindingStatus::Ignored);
    }

    /**
     * A binding row as stored, to hand back to the matcher as the current binding.
     *
     * @param  array<int, array<string, mixed>>|null  $candidates
     */
    public static function fromRow(string $status, ?int $localityId, ?string $method = null, ?string $reason = null, ?array $candidates = null): self
    {
        return new self(
            BindingStatus::from($status),
            $localityId,
            $method === null ? null : BindingMethod::tryFrom($method),
            $reason === null ? null : QueueReason::tryFrom($reason),
            array_map(static fn (array $candidate): Candidate => Candidate::fromArray($candidate), array_values($candidates ?? [])),
        );
    }

    public function isMatched(): bool
    {
        return $this->status === BindingStatus::Matched && $this->localityId !== null;
    }

    public function isManual(): bool
    {
        return $this->method === BindingMethod::Manual;
    }

    /**
     * Whether a sync leaves this binding alone: bound to a locality — by a person or by an
     * earlier match — or ignored by a person. A pending one is matched again every run, so that
     * a region or town added since resolves it.
     */
    public function isSettled(): bool
    {
        return $this->isMatched() || $this->status === BindingStatus::Ignored;
    }

    public function keep(): self
    {
        return new self($this->status, $this->localityId, $this->method, $this->reason, $this->candidates, kept: true);
    }

    /** @return array{status: string, locality_id: int|null, method: string|null, reason: string|null, candidates: list<array{locality_id: int, distance_km: float|null}>} */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'locality_id' => $this->localityId,
            'method' => $this->method?->value,
            'reason' => $this->reason?->value,
            'candidates' => array_map(static fn (Candidate $candidate): array => $candidate->toArray(), $this->candidates),
        ];
    }
}
