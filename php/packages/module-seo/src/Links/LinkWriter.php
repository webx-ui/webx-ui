<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use Illuminate\Support\Facades\DB;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Models\SeoLinkItem;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTarget;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * The one way a donor's block is written — by the panel, by an agent and by an import alike, so
 * that a link refused in one is refused in all of them for the same reason.
 */
final class LinkWriter
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public function __construct(private readonly UrlTargets $targets) {}

    /**
     * Resolve the donor and every acceptor, and say what is wrong with each line.
     *
     * @param  list<array<string, mixed>>  $items  `acceptor`, `anchor`, and optionally `line` — the
     *                                             number a problem is reported under
     * @param  bool  $strict  refuse acceptors nothing on the site answers. An import is strict: an
     *                        address in a brief that answers nothing is a typo. The panel is not:
     *                        a link that broke after it was written is shown as broken, and
     *                        refusing it would stop the editor from saving anything else.
     * @param  SeoLinkBlock|null  $into  the block being appended to, whose links count as duplicates
     */
    public function plan(string $donor, array $items, ?string $locale = null, bool $strict = false, ?SeoLinkBlock $into = null, int $donorLine = 0): LinkPlan
    {
        $problems = [];

        if (trim($donor) === '') {
            return new LinkPlan(null, [], [$this->problem($donorLine, 'donor', 'empty', self::ERROR)]);
        }

        try {
            $binding = $this->targets->resolve($donor, $locale);
        } catch (ForeignHost) {
            return new LinkPlan(null, [], [$this->problem($donorLine, 'donor', 'foreign-host', self::ERROR)]);
        }

        if ($binding->redirectedFrom !== null) {
            $problems[] = $this->problem($donorLine, 'donor', 'redirected', self::WARNING, [
                'from' => $binding->redirectedFrom,
                'to' => $this->targets->address($binding->target),
            ]);
        }

        $donorTarget = $binding->target;

        /** @var list<UrlTarget> $seen */
        $seen = $into instanceof SeoLinkBlock ? $into->items->map(static fn (SeoLinkItem $item): UrlTarget => $item->target())->all() : [];
        $kept = [];

        foreach ($items as $index => $item) {
            $line = is_int($item['line'] ?? null) ? $item['line'] : $index;
            $acceptor = is_string($item['acceptor'] ?? null) ? trim($item['acceptor']) : '';
            $anchor = is_string($item['anchor'] ?? null) ? trim($item['anchor']) : '';

            if ($acceptor === '' || $anchor === '') {
                $problems[] = $this->problem($line, $acceptor === '' ? 'acceptor' : 'anchor', 'empty', self::ERROR);

                continue;
            }

            if (mb_strlen($anchor) > 255) {
                $problems[] = $this->problem($line, 'anchor', 'anchor-long', self::ERROR);

                continue;
            }

            try {
                $found = $this->targets->resolve($acceptor, $donorTarget->locale);
            } catch (ForeignHost) {
                $problems[] = $this->problem($line, 'acceptor', 'foreign-host', self::ERROR);

                continue;
            }

            $target = $found->target;

            if ($target->sameAs($donorTarget)) {
                $problems[] = $this->problem($line, 'acceptor', 'self', self::ERROR);

                continue;
            }

            if (array_filter($seen, static fn (UrlTarget $other): bool => $other->sameAs($target)) !== []) {
                $problems[] = $this->problem($line, 'acceptor', 'duplicate', self::ERROR);

                continue;
            }

            if ($strict && ! $target->isBound() && $this->targets->isBroken($target)) {
                $problems[] = $this->problem($line, 'acceptor', 'not-found', self::ERROR);

                continue;
            }

            if ($found->redirectedFrom !== null) {
                $problems[] = $this->problem($line, 'acceptor', 'redirected', self::WARNING, [
                    'from' => $found->redirectedFrom,
                    'to' => $this->targets->address($target),
                ]);
            }

            $seen[] = $target;
            $kept[] = ['target' => $target, 'anchor' => $anchor, 'line' => $line];
        }

        return new LinkPlan($binding, $kept, $problems);
    }

    /** The block another donor already holds for this address, if any. */
    public function blockFor(UrlTarget $donor, ?int $except = null): ?SeoLinkBlock
    {
        return SeoLinkBlock::query()
            ->ofTarget($donor)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except))
            ->first();
    }

    /**
     * Write what the plan kept: the links replace the block's (or follow them, appending).
     *
     * @param  array{heading?: string|null, is_active?: bool}  $attributes  only what is given is set
     */
    public function save(LinkPlan $plan, array $attributes = [], bool $append = false, ?SeoLinkBlock $block = null): SeoLinkBlock
    {
        $donor = $plan->donor?->target;

        if (! $donor instanceof UrlTarget) {
            throw new \LogicException('A plan without a donor cannot be saved.');
        }

        return DB::transaction(function () use ($plan, $attributes, $append, $block, $donor): SeoLinkBlock {
            $block ??= $this->blockFor($donor) ?? new SeoLinkBlock;
            $block->fill($donor->toRow());

            if (array_key_exists('heading', $attributes)) {
                $heading = is_string($attributes['heading']) ? trim($attributes['heading']) : '';
                $block->heading = $heading === '' ? null : mb_substr($heading, 0, 255);
            }

            if (array_key_exists('is_active', $attributes)) {
                $block->is_active = (bool) $attributes['is_active'];
            }

            $block->save();

            $position = 0;

            if ($append) {
                $position = (int) SeoLinkItem::query()->where('block_id', $block->id)->max('position') + 1;
            } else {
                SeoLinkItem::query()->where('block_id', $block->id)->delete();
            }

            foreach ($plan->items as $item) {
                SeoLinkItem::query()->create($item['target']->toRow() + [
                    'block_id' => $block->id,
                    'anchor' => $item['anchor'],
                    'position' => $position++,
                ]);
            }

            return $block->refresh();
        });
    }

    /**
     * @param  array<string, string>  $replace
     * @return array{line: int, field: string, code: string, level: string, message: string}
     */
    private function problem(int $line, string $field, string $code, string $level, array $replace = []): array
    {
        return [
            'line' => $line,
            'field' => $field,
            'code' => $code,
            'level' => $level,
            'message' => (string) __('webx-seo::links.'.$code, $replace),
        ];
    }
}
