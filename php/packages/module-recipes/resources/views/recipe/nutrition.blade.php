{{-- Only the values written in this language; none — no table. --}}
@if ($nutrition !== [])
    <section class="wx-recipe__nutrition">
        <h2>{{ trans('webx-recipes::site.nutrition') }}</h2>
        <table>
            @foreach ($nutrition as $key => $value)
                <tr>
                    <th scope="row">{{ trans('webx-recipes::site.nutrition-'.$key) }}</th>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </section>
@endif
