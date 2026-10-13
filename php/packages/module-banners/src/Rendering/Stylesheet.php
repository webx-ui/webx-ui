<?php

declare(strict_types=1);

namespace WebxUi\Banners\Rendering;

/**
 * The CSS and the two small scripts a banner with layers needs, printed once a page however many
 * banners it has (§4.2 of the layers spec).
 *
 * `layers.css` is the one file: the panel's canvas imports it as it is. The phone's layout in it
 * is written for the class `is-mobile` the canvas switches; the site prints that block once more
 * per breakpoint, inside `@media (max-width: …)`, aimed at the frames of that breakpoint — the
 * width `<picture>` switches its source at, so the layers never stand on the wrong picture.
 *
 * Scoped to the request: a long-lived worker that kept it would print nothing on the second page.
 */
final class Stylesheet
{
    private bool $base = false;

    private bool $scripts = false;

    /** @var array<int, true> */
    private array $breakpoints = [];

    /** The base rules, the first time a page asks; '' after that. */
    public function base(): string
    {
        if ($this->base) {
            return '';
        }

        $this->base = true;

        return self::source();
    }

    /** The phone's layout below this breakpoint, the first time a page asks for it; '' after. */
    public function breakpoint(int $breakpoint): string
    {
        if ($breakpoint < 2 || isset($this->breakpoints[$breakpoint])) {
            return '';
        }

        $this->breakpoints[$breakpoint] = true;

        if (preg_match('~/\* mobile:start \*/(.*?)/\* mobile:end \*/~s', self::source(), $block) !== 1) {
            return '';
        }

        $rules = str_replace('.webx-banner.is-mobile', '.webx-banner[data-breakpoint="'.$breakpoint.'"]', $block[1]);

        return '@media (max-width: '.($breakpoint - 1).'px) {'.$rules.'}';
    }

    /**
     * What plays a banner's video where it is wanted and opens a random banner of a `random`
     * place — once a page. Without it, the picture is seen, and the first banner of a place.
     */
    public function scripts(): string
    {
        if ($this->scripts) {
            return '';
        }

        $this->scripts = true;

        return <<<'JS'
            (() => {
              const run = () => {
              const still = matchMedia('(prefers-reduced-motion: reduce)').matches
              document.querySelectorAll('.webx-banner__video[data-src]').forEach((video) => {
                const wide = matchMedia(`(min-width: ${video.dataset.breakpoint || 0}px)`).matches
                if (still || (!wide && video.dataset.onMobile !== 'true')) return
                video.src = video.dataset.src
                video.removeAttribute('data-src')
                video.play().catch(() => {})
              })
              document.querySelectorAll('.webx-banners__strip').forEach((strip) => {
                const place = strip.parentElement
                const items = [...strip.querySelectorAll(':scope > .webx-banners__item')]
                const controls = place.querySelector('.webx-banners__controls')
                const dots = place.querySelector('.webx-banners__dots')
                const loop = strip.dataset.loop === 'true'
                let current = 0
                let paused = false
                const go = (index) => {
                  if (index < 0 || index >= items.length) {
                    if (!loop) return
                    index = (index + items.length) % items.length
                  }
                  strip.scrollTo({ left: items[index].offsetLeft - strip.offsetLeft, behavior: still ? 'auto' : 'smooth' })
                }
                const mark = (index) => {
                  current = index
                  items.forEach((item, at) => item.classList.toggle('is-active', at === index))
                  dots?.querySelectorAll('button').forEach((dot, at) => dot.setAttribute('aria-current', at === index ? 'true' : 'false'))
                }
                if (dots) items.forEach((item, at) => {
                  const dot = document.createElement('button')
                  dot.type = 'button'
                  dot.className = 'webx-banners__dot'
                  dot.setAttribute('aria-label', item.getAttribute('aria-label') || String(at + 1))
                  dot.addEventListener('click', () => go(at))
                  dots.append(dot)
                })
                place.querySelector('.webx-banners__prev')?.addEventListener('click', () => go(current - 1))
                place.querySelector('.webx-banners__next')?.addEventListener('click', () => go(current + 1))
                const seen = new IntersectionObserver((entries) => entries.forEach((entry) => {
                  if (entry.intersectionRatio > 0.6) mark(items.indexOf(entry.target))
                }), { root: strip, threshold: [0.6] })
                items.forEach((item) => seen.observe(item))
                const interval = Number(strip.dataset.interval)
                if (interval > 0 && !still) {
                  setInterval(() => paused || document.hidden || go(current + 1), interval)
                  if (strip.dataset.pause === 'true') {
                    place.addEventListener('pointerenter', () => (paused = true))
                    place.addEventListener('pointerleave', () => (paused = false))
                  }
                  place.addEventListener('focusin', () => (paused = true))
                  place.addEventListener('focusout', () => (paused = false))
                }
                mark(0)
                strip.classList.add('is-ready')
                if (controls) controls.hidden = false
              })
              document.querySelectorAll('.webx-banners--random').forEach((place) => {
                const items = [...place.querySelectorAll(':scope > .webx-banners__item')]
                if (items.length < 2) return
                const pick = items[Math.floor(Math.random() * items.length)]
                items.forEach((item) => (item.hidden = item !== pick))
              })
              }
              // Printed with the first banner of the page: the rest are not there yet.
              if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run)
              else run()
            })()
            JS;
    }

    private static function source(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/css/layers.css');
    }
}
