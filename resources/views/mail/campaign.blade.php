<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;-webkit-text-size-adjust:100%;">
    @if ($preheader !== '')
        <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $preheader }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:8px;">
                    <tr>
                        <td style="padding:24px 32px 8px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#4b5563;">{{ $listName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>
                    @if ($downloads !== [])
                        <tr>
                            <td style="padding:0 32px 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;">
                                <p style="margin:0 0 8px;font-weight:bold;">{{ __('mailing::mail.campaign.downloads') }}</p>
                                @foreach ($downloads as $download)
                                    <p style="margin:0 0 4px;"><a href="{{ $download['url'] }}" style="color:#1d4ed8;text-decoration:underline;">{{ $download['name'] }}</a> ({{ $download['size'] }})</p>
                                @endforeach
                                <p style="margin:8px 0 0;font-size:13px;color:#6b7280;">{{ __('mailing::mail.campaign.downloads_until', ['date' => $downloadsUntil]) }}</p>
                            </td>
                        </tr>
                    @endif
                </table>
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
                    <tr>
                        <td style="padding:16px 32px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#6b7280;text-align:center;">
                            <p style="margin:0 0 8px;">{{ __('mailing::mail.campaign.sender', ['sender' => $senderName]) }}</p>
                            <p style="margin:0 0 8px;">{{ __('mailing::mail.campaign.reason', ['list' => $listName]) }}</p>
                            @if (! is_null($unsubscribeUrl))
                                <p style="margin:0;"><a href="{{ $unsubscribeUrl }}" style="color:#6b7280;text-decoration:underline;">{{ __('mailing::mail.campaign.unsubscribe') }}</a></p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
