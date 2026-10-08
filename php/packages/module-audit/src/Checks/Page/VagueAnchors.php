<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * Links called "here", "read more", "подробнее": the anchor is what search engines and screen
 * readers learn about the page behind it, and these say nothing. The words of every language
 * the panel ships are built in; a site adds its own in the settings or in the config.
 */
final class VagueAnchors extends LinkCheck
{
    protected const ID = 'links.vague_anchor';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'links-vague-anchor';

    /** Longer than this, an anchor is a sentence, not "read more". */
    private const LONGEST = 40;

    /** @var array<string, true>|null */
    private ?array $words = null;

    public function run(AuditContext $context): iterable
    {
        $this->words = null;

        yield from parent::run($context);
    }

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)->where('kind', AuditLink::A)->whereNotNull('anchor');
    }

    protected function keep(AuditLink $link, AuditContext $context): bool
    {
        $anchor = (string) $link->anchor;

        if (mb_strlen($anchor) > self::LONGEST) {
            return false;
        }

        $this->words ??= array_fill_keys(array_map(
            self::normalise(...),
            [
                ...VagueWords::ALL,
                // The settings screen laid over the file; the file alone where nothing laid it.
                ...array_filter((array) $context->config('webx-audit.vague_anchors_all', $context->config('webx-audit.vague_anchors', [])), 'is_string'),
            ],
        ), true);

        return isset($this->words[self::normalise($anchor)]);
    }

    /** Lowercase, single spaces, without the arrows and dots around it: "Read more →" is "read more". */
    public static function normalise(string $anchor): string
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $anchor)));

        return trim((string) preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $text));
    }
}
