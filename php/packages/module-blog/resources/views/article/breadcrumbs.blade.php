{{-- Feed, main rubric, the article: the same list as the BreadcrumbList in the <head>. --}}@if (config('webx-blog.breadcrumbs', true))
<x-webx-seo::breadcrumbs :for="$article" />
@endif
