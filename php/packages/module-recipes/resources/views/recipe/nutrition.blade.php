{{-- Only the values written in this language; none — no table. --}}
@if ($nutrition !== [])
    <section class="wx-recipe__nutrition">
        <h2 class="wx-recipe__section-title">{{ trans('webx-recipes::site.nutrition') }}</h2>
        <table class="wx-recipe__table">
            @foreach ($nutrition as $key => $value)
                <tr class="wx-recipe__row">
                    <th class="wx-recipe__nutrient" scope="row">{{ trans('webx-recipes::site.nutrition-'.$key) }}</th>
                    <td class="wx-recipe__amount">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </section>
@endif
