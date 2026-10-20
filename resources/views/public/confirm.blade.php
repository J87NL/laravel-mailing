@extends(config('mailing.layout'))

@section('title', __(sprintf('mailing::confirm.%s.title', $state)))

@section('head')
    <meta name="robots" content="noindex">
    @if ($state === 'confirm')
        <script src="{{ route('mailing.confirm.script') }}" defer></script>
    @endif
@endsection

@section('content')
    <article class="mailing-page">
        <h1 class="mailing-page__title">{{ __(sprintf('mailing::confirm.%s.title', $state)) }}</h1>

        <p class="mailing-page__text">{{ __(sprintf('mailing::confirm.%s.text', $state), ['list' => $listName ?? '']) }}</p>

        @if ($state === 'confirm')
            <form class="mailing-page__form" method="post" action="{{ $action }}" {{ $autoSubmitAttribute }}>
                <p class="mailing-signup__actions">
                    <button type="submit" class="mailing-signup__button">{{ __('mailing::confirm.confirm.button') }}</button>
                </p>
            </form>
        @endif

        @if ($state === 'expired' && ! is_null($signupUrl))
            <p class="mailing-page__text">
                <a href="{{ $signupUrl }}">{{ __('mailing::confirm.expired.again') }}</a>
            </p>
        @endif
    </article>
@endsection
