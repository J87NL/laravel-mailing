{!! __('mailing::mail.confirm.greeting') !!}

{!! __('mailing::mail.confirm.intro', ['list' => $listName]) !!}

{!! __('mailing::mail.confirm.button') !!}: {!! $confirmUrl !!}

{!! __('mailing::mail.confirm.valid', ['days' => $validDays]) !!}

{!! __('mailing::mail.confirm.ignore') !!}
