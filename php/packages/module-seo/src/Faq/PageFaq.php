<?php

declare(strict_types=1);

namespace WebxUi\Seo\Faq;

use Illuminate\Support\Facades\DB;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Models\SeoFaqItem;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * The FAQ of a page (§18.5): what the page prints, what its markup says, and the one way the
 * questions are written — by the panel, by an agent and by an import alike.
 */
final class PageFaq
{
    /** The key `Seo::put()` and `Seo::blocks()` know a FAQ block by — the one `module-faq` uses. */
    public const KEY = 'faq';

    public function __construct(private readonly UrlTargets $targets) {}

    /**
     * The questions of a rule that have both halves in this language, in order.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function questions(SeoUrl $rule, string $locale): array
    {
        if ($rule->match_type !== UrlMatcher::EXACT) {
            return [];
        }

        $questions = [];

        foreach ($rule->faqItems as $item) {
            $question = $item->questionText($locale);
            $answer = $item->answerHtml($locale);

            if ($question !== '' && self::plain($answer) !== '') {
                $questions[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $questions;
    }

    /**
     * The `FAQPage` block of these questions, or null when there are none.
     *
     * @param  list<array{question: string, answer: string}>  $questions
     * @return array<string, mixed>|null
     */
    public function markup(array $questions): ?array
    {
        if ($questions === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $question): array => [
                '@type' => 'Question',
                'name' => $question['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => self::plain($question['answer'])],
            ], $questions),
        ];
    }

    /**
     * Every block of the page that is a `FAQPage`, folded into the first of them (§18.5): the
     * rule's questions and the ones `module-faq` put beside them are one FAQ to a search engine.
     * A question asked twice — by its text, as a reader would see it — is kept where it came first.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function fold(array $blocks): array
    {
        $first = null;
        $seen = [];
        $folded = [];

        foreach ($blocks as $block) {
            if (($block['@type'] ?? null) !== 'FAQPage') {
                $folded[] = $block;

                continue;
            }

            $entities = is_array($block['mainEntity'] ?? null) ? array_values($block['mainEntity']) : [];
            $kept = [];

            foreach ($entities as $entity) {
                $name = is_array($entity) && is_string($entity['name'] ?? null) ? self::key($entity['name']) : null;

                if ($name !== null && isset($seen[$name])) {
                    continue;
                }

                if ($name !== null) {
                    $seen[$name] = true;
                }

                $kept[] = $entity;
            }

            if ($first === null) {
                $first = count($folded);
                $block['mainEntity'] = $kept;
                $folded[] = $block;
            } else {
                $folded[$first]['mainEntity'] = [...(array) $folded[$first]['mainEntity'], ...$kept];
            }
        }

        if ($first !== null && $folded[$first]['mainEntity'] === []) {
            array_splice($folded, $first, 1);
        }

        return $folded;
    }

    /**
     * The FAQ as the panel edits it: every language of both halves.
     *
     * @return list<array{id: int, question: object, answer: object}>
     */
    public function describe(SeoUrl $rule): array
    {
        return $rule->faqItems->map(static fn (SeoFaqItem $item): array => [
            'id' => $item->id,
            'question' => (object) $item->getTranslations('question'),
            'answer' => (object) $item->getTranslations('answer'),
        ])->values()->all();
    }

    /**
     * Write the questions of a rule: in place of the ones it has, or after them.
     *
     * @param  list<array{question: array<string, string>, answer: array<string, string>}>  $items
     */
    public function write(SeoUrl $rule, array $items, bool $append = false): void
    {
        DB::transaction(static function () use ($rule, $items, $append): void {
            $position = 0;

            if ($append) {
                $position = (int) $rule->faqItems()->max('position') + 1;
            } else {
                $rule->faqItems()->delete();
            }

            foreach ($items as $item) {
                SeoFaqItem::query()->create([
                    'seo_url_id' => $rule->id,
                    'question' => $item['question'],
                    'answer' => array_map(self::html(...), $item['answer']),
                    'position' => $position++,
                ]);
            }
        });

        $rule->unsetRelation('faqItems');
    }

    /**
     * The active exact rule of an address, the way the site would match it now — or null.
     *
     * @throws ForeignHost
     */
    public function ruleFor(string $address, ?string $locale = null): ?SeoUrl
    {
        $wanted = $this->targets->address($this->targets->resolve($address, $locale)->target);

        return SeoUrl::query()
            ->active()
            ->where('match_type', UrlMatcher::EXACT)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(static fn (SeoUrl $rule): bool => UrlNormaliser::normalise($rule->currentPattern()) === $wanted);
    }

    /**
     * The address and its language as a page FAQ is written for it: the redirect followed, the
     * host taken off, the current spelling of a page with an entity.
     *
     * @return array{address: string, locale: string, redirected_from: string|null}
     *
     * @throws ForeignHost
     */
    public function address(string $address, ?string $locale = null): array
    {
        $binding = $this->targets->resolve($address, $locale);

        return [
            'address' => $this->targets->address($binding->target),
            'locale' => $binding->target->locale,
            'redirected_from' => $binding->redirectedFrom,
        ];
    }

    /** A new exact rule for an address, with nothing in its meta fields (§18.5). */
    public function newRule(string $address): SeoUrl
    {
        return SeoUrl::query()->create([
            'match_type' => UrlMatcher::EXACT,
            'pattern' => UrlNormaliser::normalise($address),
            'priority' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * An answer as text: tags gone, a line break where a paragraph or an item ended, entities
     * decoded — what the markup says, the way `module-faq` says it.
     */
    public static function plain(string $html): string
    {
        $text = strip_tags(str_replace(
            ['</p>', '<br>', '<br/>', '<br />', '</li>'],
            ["</p>\n", "\n", "\n", "\n", "</li>\n"],
            $html,
        ));

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    /**
     * An answer as the page prints it. A spreadsheet or an agent may send plain text, and plain
     * text printed raw would let its `<` start a tag: it becomes paragraphs, escaped.
     */
    public static function html(string $answer): string
    {
        $answer = trim($answer);

        if ($answer === '' || preg_match('/<[a-z][a-z0-9]*[\s>\/]/i', $answer) === 1) {
            return $answer;
        }

        $paragraphs = preg_split('/\R{2,}/u', $answer) ?: [$answer];

        return implode('', array_map(
            static fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>',
            $paragraphs,
        ));
    }

    /** A question as a reader tells two apart: case and spacing do not make another one. */
    public static function key(string $question): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $question)), 'UTF-8');
    }
}
