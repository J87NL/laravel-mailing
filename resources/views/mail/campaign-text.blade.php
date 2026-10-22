{!! $bodyText !!}
@if ($downloads !== [])

{!! __('mailing::mail.campaign.downloads') !!}
@foreach ($downloads as $download)
- {!! $download['name'] !!} ({!! $download['size'] !!}): {!! $download['url'] !!}
@endforeach
{!! __('mailing::mail.campaign.downloads_until', ['date' => $downloadsUntil]) !!}
@endif

--
{!! __('mailing::mail.campaign.sender', ['sender' => $senderName]) !!}
{!! __('mailing::mail.campaign.reason', ['list' => $listName]) !!}
@if (! is_null($unsubscribeUrl))
{!! __('mailing::mail.campaign.unsubscribe') !!}: {!! $unsubscribeUrl !!}
@endif
