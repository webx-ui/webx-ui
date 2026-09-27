<p class="wx-press-outlet__facts">
    <span class="wx-press-outlet__count">{{ trans('webx-press::site.articles-count', ['count' => count($articles)]) }}</span>
    @if ($website)
        · <a class="wx-press-outlet__website" href="{{ $website }}" target="_blank" rel="noopener">{{ trans('webx-press::site.visit') }}</a>
    @endif
</p>
