{{--
    The page of a product that is on sale (§5, §10.1). The least markup that works, meant to be
    overridden by the site in `resources/views/vendor/webx-catalog/product.blade.php`: a product
    page is the part of a shop that looks like the shop.

    `$verdict` is Purchasability's answer: "Buy" when it says yes, "Ask the price" for a price on
    request, and the reason otherwise. There is no cart yet, so the buttons are the site's to wire.

    A picture with a video attached is its poster and a ▶ (§6 of the video spec): the player is put
    in on the click, so that nothing of YouTube is loaded and no cookie is set before it. Without
    JS the ▶ is a link to the file or to the video's page.

    Two points for the satellites: `catalog.product.aside` beside the price (the stock, the brand)
    and `catalog.product.tabs` under the description (properties, related products).
--}}
@php($priced = (bool) config('webx-catalog.price.enabled', true))
@php($images = $product->images)
@php($videos = (bool) config('webx-catalog.fields.video', true))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        {{-- The SEO card, then the product's own name, summary and main picture where the card is
             empty (`seoFallback()`), then the site defaults. --}}
        @webxSeo($product)
    </x-slot:head>

    <article class="webx-catalog-product">
        <header>
            @include('webx-catalog::breadcrumbs', ['for' => $product])
            <h1>{{ $product->displayName() }}</h1>
            @if ($product->sku !== null)
                <p class="webx-catalog-product__sku">{{ __('webx-catalog::storefront.sku') }}: {{ $product->sku }}</p>
            @endif
        </header>

        @if ($images->isNotEmpty())
            <div class="webx-catalog-product__gallery">
                @foreach ($images as $image)
                    @php($video = $videos ? $image->videoData() : null)
                    @if ($video !== null)
                        <figure class="webx-catalog-product__video">
                    @endif
                    <img src="{{ $image->url() }}"
                         alt="{{ $image->getTranslation('alt') ?: $product->displayName() }}"
                         @if ($image->getTranslation('title')) title="{{ $image->getTranslation('title') }}" @endif
                         @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif
                         @if (! $loop->first) loading="lazy" @endif>
                    @if ($video !== null)
                            <a class="webx-catalog-product__play"
                               href="{{ $video['url'] }}"
                               @if ($video['embed'] !== null)
                                   data-webx-embed="{{ $video['embed'] }}" target="_blank" rel="noopener"
                               @else
                                   data-webx-video="{{ $video['url'] }}" data-webx-poster="{{ $image->url() }}"
                               @endif
                               aria-label="{{ __('webx-catalog::storefront.play-video') }}">▶</a>
                        </figure>
                    @endif
                @endforeach
            </div>
            @if ($videos && $images->contains(fn ($image) => $image->hasVideo()))
                <script>
                    // The player only on a click (§6 of the video spec): until then the page loads
                    // nothing of YouTube and plays nothing of its own.
                    document.addEventListener('click', function (event) {
                        var play = event.target instanceof Element ? event.target.closest('.webx-catalog-product__play') : null;
                        if (!play) return;
                        var figure = play.closest('.webx-catalog-product__video');
                        var player;
                        if (play.dataset.webxEmbed) {
                            player = document.createElement('iframe');
                            player.src = play.dataset.webxEmbed + (play.dataset.webxEmbed.indexOf('?') < 0 ? '?' : '&') + 'autoplay=1';
                            player.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
                            player.allowFullscreen = true;
                            player.title = play.getAttribute('aria-label') || '';
                        } else if (play.dataset.webxVideo) {
                            player = document.createElement('video');
                            player.src = play.dataset.webxVideo;
                            player.poster = play.dataset.webxPoster || '';
                            player.controls = true;
                            player.autoplay = true;
                            player.playsInline = true;
                            player.preload = 'none';
                        }
                        if (!player || !figure) return;
                        event.preventDefault();
                        var picture = figure.querySelector('img');
                        if (picture && picture.width) {
                            player.width = picture.width;
                            player.height = picture.height;
                        }
                        player.className = 'webx-catalog-product__player';
                        figure.replaceChildren(player);
                    });
                </script>
            @endif
        @endif

        <div class="webx-catalog-product__buy">
            @if ($priced)
                <p class="webx-catalog-product__price">
                    @if ($product->price !== null)
                        @if ($product->old_price !== null)
                            <s>{{ $product->old_price }}</s>
                        @endif
                        <strong>{{ $product->price }}</strong>
                        @if ($product->unit !== null)
                            / {{ __('webx-catalog::units.'.$product->unit) }}
                        @endif
                    @else
                        {{ __('webx-catalog::storefront.price-on-request') }}
                    @endif
                </p>
            @endif

            @if ($verdict->purchasable)
                <button type="button" class="webx-catalog-product__action" data-product="{{ $product->id }}">{{ __('webx-catalog::storefront.buy') }}</button>
            @elseif ($verdict->code === WebxUi\Catalog\Purchase\CoreRules::PRICE_ON_REQUEST)
                <button type="button" class="webx-catalog-product__action" data-product="{{ $product->id }}">{{ __('webx-catalog::storefront.ask-price') }}</button>
            @else
                <p class="webx-catalog-product__refusal">{{ $verdict->label }}</p>
            @endif

            @webxPart('catalog.product.aside', ['product' => $product, 'verdict' => $verdict], 'webx-catalog::points.product-aside')
        </div>

        @if ($product->summary)
            <p class="webx-catalog-product__summary">{{ $product->summary }}</p>
        @endif

        @if ($product->description)
            <div class="webx-catalog-product__description">{!! $product->description !!}</div>
        @endif

        @webxPart('catalog.product.tabs', ['product' => $product], 'webx-catalog::points.product-tabs')
    </article>
</x-dynamic-component>
