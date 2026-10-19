<section class="mailing-signup" id="{{ $anchor }}">
    @if ($title !== '')
        <h2 class="mailing-signup__title">{{ $title }}</h2>
    @endif

    @if ($subscribed)
        <p class="mailing-signup__success" role="status">{{ __('mailing::signup.check_inbox') }}</p>
    @else
        @if ($intro !== '')
            <p class="mailing-signup__intro">{{ $intro }}</p>
        @endif

        @if ($errors->isNotEmpty())
            <div class="mailing-signup__errors" role="alert">
                <p>{{ __('mailing::signup.has_errors') }}</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="mailing-signup__form" method="post" action="{{ $action }}">
            <p class="mailing-signup__field">
                <label for="{{ $anchor }}-email">{{ __('mailing::signup.email') }}</label>
                <input id="{{ $anchor }}-email" type="email" name="email" value="{{ $old['email'] ?? '' }}" autocomplete="email" maxlength="254" required>
            </p>

            <p class="mailing-signup__field">
                <label for="{{ $anchor }}-name">{{ __('mailing::signup.name_optional') }}</label>
                <input id="{{ $anchor }}-name" type="text" name="name" value="{{ $old['name'] ?? '' }}" autocomplete="name" maxlength="120">
            </p>

            <p class="mailing-signup__trap" aria-hidden="true">
                <label for="{{ $anchor }}-website">{{ __('mailing::signup.trap') }}</label>
                <input id="{{ $anchor }}-website" type="text" name="{{ \J87NL\Mailing\Signup\SignupGuard::HONEYPOT }}" tabindex="-1" autocomplete="off">
            </p>

            <input type="hidden" name="{{ \J87NL\Mailing\Signup\SignupGuard::TIMESTAMP }}" value="{{ $token }}">

            @if (! is_null($returnTo))
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif

            <p class="mailing-signup__consent">{{ __('mailing::signup.consent', ['list' => $list->name]) }}</p>

            @if (! empty($privacyUrl))
                <p class="mailing-signup__privacy">
                    {{ __('mailing::signup.privacy_before') }}
                    <a href="{{ $privacyUrl }}">{{ __('mailing::signup.privacy_link') }}</a>.
                </p>
            @endif

            <p class="mailing-signup__actions">
                <button type="submit" class="mailing-signup__button">{{ $buttonLabel }}</button>
            </p>
        </form>
    @endif
</section>
