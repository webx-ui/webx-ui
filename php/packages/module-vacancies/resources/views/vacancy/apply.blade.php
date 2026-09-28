{{--
    The place for the application — empty in the package (§7, item 2 of the spec). `$form` is the
    slug of the form chosen in the vacancy when it is switched on, or null. A site that has
    `module-inbox` publishes this part and prints the form itself:

        @if ($form)
            <section class="wx-vacancy__apply">
                <h2>Apply</h2>
                <x-webx-inbox::form :slug="$form" :values="['vacancy' => $title]" />
            </section>
        @endif

    `:values` fills a hidden field named `vacancy`, if the form has one, so a submission says which
    vacancy it answered. Not drawn at all on a closed vacancy.
--}}
