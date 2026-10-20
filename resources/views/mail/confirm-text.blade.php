{!! __('mailing::mail.confirm.greeting') !!}

{!! __(sprintf('mailing::mail.%s.intro', $copy), ['list' => $listName]) !!}

{!! __('mailing::mail.confirm.button') !!}: {!! $confirmUrl !!}

{!! __('mailing::mail.confirm.valid', ['days' => $validDays]) !!}

{!! __('mailing::mail.confirm.ignore') !!}
