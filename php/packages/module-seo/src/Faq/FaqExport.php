<?php

declare(strict_types=1);

namespace WebxUi\Seo\Faq;

use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * Every page FAQ as the flat file an import reads (§18.5): one line per question, in the
 * language of its address. Addresses are the current ones — what the site prints now.
 */
final class FaqExport
{
    public function __construct(private readonly UrlTargets $targets) {}

    public function write(string $path, string $format): void
    {
        FaqSpreadsheet::write($path, $format, $this->rows());
    }

    /**
     * @return iterable<list<string>>
     */
    public function rows(): iterable
    {
        yield FaqSpreadsheet::COLUMNS;

        $rules = SeoUrl::query()
            ->where('match_type', UrlMatcher::EXACT)
            ->whereHas('faqItems')
            ->with('faqItems')
            ->orderBy('id')
            ->lazy();

        foreach ($rules as $rule) {
            $address = $rule->currentPattern();
            [$locale] = $this->targets->split($address, null);

            foreach ($rule->faqItems as $item) {
                $question = $item->questionText($locale);
                $answer = $item->getTranslation('answer', $locale, fallback: false);

                if ($question !== '' && is_string($answer) && $answer !== '') {
                    yield [$address, $question, $answer];
                }
            }
        }
    }
}
