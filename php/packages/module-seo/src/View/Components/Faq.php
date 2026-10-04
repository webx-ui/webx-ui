<?php

declare(strict_types=1);

namespace WebxUi\Seo\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Features;
use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Rendering\Seo;

/**
 * `<x-webx-seo::faq />` — the questions of the exact rule of the page being answered (§18.5).
 *
 * Nothing at all when the feature is off or the page has no questions, so a template can carry
 * the tag on every page. The markup is the `webx-seo::faq` view, which a site publishes and
 * restyles; the `FAQPage` block is the rule's and is in `<head>` whether or not this is printed.
 */
final class Faq extends Component
{
    public function __construct(
        private readonly PageFaq $faq,
        private readonly UrlRuleSource $rules,
        private readonly Seo $seo,
        private readonly Locales $locales,
    ) {}

    public function render(): View|string
    {
        if (! Features::faq()) {
            return '';
        }

        $rule = $this->rules->matching($this->seo->currentUrl());
        $questions = $rule === null ? [] : $this->faq->questions($rule, $this->locales->current());

        return $questions === [] ? '' : view('webx-seo::faq', [
            'questions' => $questions,
            'id' => 'webx-faq-'.$rule?->id,
        ]);
    }
}
