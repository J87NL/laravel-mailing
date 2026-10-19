@extends(config('mailing.layout'))

@section('title', __(sprintf('mailing::unsubscribe.%s.title', $state)))

@section('head')
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <article class="mailing-page">
        <h1 class="mailing-page__title">{{ __(sprintf('mailing::unsubscribe.%s.title', $state)) }}</h1>

        @if ($state === 'choose')
            <p class="mailing-page__text">{{ __('mailing::unsubscribe.choose.text', ['list' => $listName]) }}</p>

            <form class="mailing-page__form" method="post" action="{{ $action }}">
                <input type="hidden" name="scope" value="{{ \J87NL\Mailing\Http\Controllers\Public\UnsubscribeController::SCOPE_LIST }}">
                <p class="mailing-signup__actions">
                    <button type="submit" class="mailing-signup__button">{{ __('mailing::unsubscribe.choose.this_list', ['list' => $listName]) }}</button>
                </p>
            </form>

            <form class="mailing-page__form" method="post" action="{{ $action }}">
                <input type="hidden" name="scope" value="{{ \J87NL\Mailing\Http\Controllers\Public\UnsubscribeController::SCOPE_ALL }}">
                <p class="mailing-signup__actions">
                    <button type="submit" class="mailing-signup__button mailing-signup__button--secondary">{{ __('mailing::unsubscribe.choose.all_lists') }}</button>
                </p>
            </form>
        @else
            <p class="mailing-page__text" role="status">{{ __(sprintf('mailing::unsubscribe.done.%s', $scope), ['list' => $listName]) }}</p>
        @endif
    </article>
@endsection
