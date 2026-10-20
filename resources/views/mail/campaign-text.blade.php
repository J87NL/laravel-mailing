{!! $bodyText !!}

--
{!! __('mailing::mail.campaign.sender', ['sender' => $senderName]) !!}
{!! __('mailing::mail.campaign.reason', ['list' => $listName]) !!}
@if (! is_null($unsubscribeUrl))
{!! __('mailing::mail.campaign.unsubscribe') !!}: {!! $unsubscribeUrl !!}
@endif
