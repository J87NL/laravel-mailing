@extends(config('mailing.layout'))

@section('title', __('mailing::signup.page_title', ['list' => $list->name]))

@section('head')
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <article class="mailing-page">
        <h1 class="mailing-page__title">{{ __('mailing::signup.page_title', ['list' => $list->name]) }}</h1>

        @include('mailing::partials.signup-form', $form)
    </article>
@endsection
