{{-- The FAQ of a page: the questions of its exact SEO rule, each one a native disclosure, so it
     opens without a script. Publish and restyle it as you like (`webx-seo-views`). The answer is
     the editor's rich text and is printed as written; the question is plain text. --}}
<section class="webx-faq" aria-labelledby="{{ $id }}">
    <h2 id="{{ $id }}" class="webx-faq__heading">{{ __('webx-seo::site.faq-heading') }}</h2>
    @foreach ($questions as $question)
        <details class="webx-faq__item">
            <summary class="webx-faq__question">{{ $question['question'] }}</summary>
            <div class="webx-faq__answer">{!! $question['answer'] !!}</div>
        </details>
    @endforeach
</section>
