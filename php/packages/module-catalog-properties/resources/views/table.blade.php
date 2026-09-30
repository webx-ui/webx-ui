{{--
    «Specifications» on the page of a product: the properties marked «on the page», by group in
    the order of the set, those without a group last. A value of a reference book whose first
    level is an open page of the category links to it. A site that wants its own table publishes
    this view (`webx-catalog-properties-views`).
--}}
@php($specGroups = isset($product, $productProperties) ? $productProperties->table($product) : [])
@if ($specGroups !== [])
    <section class="wx-catalog-specs-section">
        <h2>{{ __('webx-catalog-properties::product.tab') }}</h2>
        @foreach ($specGroups as $specGroup)
            @if ($specGroup['group'] !== null)
                <h3>{{ $specGroup['group']['title'] }}</h3>
            @endif
            <dl class="wx-catalog-specs">
                @foreach ($specGroup['properties'] as $shown)
                    <div class="wx-catalog-specs__row" data-property="{{ $shown->code }}">
                        <dt>{{ $shown->label }}</dt>
                        <dd>
                            @if ($shown->values !== [])
                                @foreach ($shown->values as $one)
                                    @if ($one['url'] !== null)<a href="{{ $one['url'] }}">{{ $one['label'] }}</a>@else{{ $one['label'] }}@endif{{ $loop->last ? '' : ', ' }}
                                @endforeach
                            @else
                                {{ $shown->formatted }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endforeach
    </section>
@endif
