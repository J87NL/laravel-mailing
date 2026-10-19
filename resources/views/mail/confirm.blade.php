<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mailing::mail.confirm.subject', ['list' => $listName]) }}</title>
</head>
<body style="margin:0;padding:24px;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:8px;">
                    <tr>
                        <td style="padding:32px;font-size:16px;line-height:1.5;">
                            <p style="margin:0 0 16px;">{{ __('mailing::mail.confirm.greeting') }}</p>
                            <p style="margin:0 0 24px;">{{ __('mailing::mail.confirm.intro', ['list' => $listName]) }}</p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $confirmUrl }}" style="display:inline-block;padding:12px 20px;background:#1f2937;color:#ffffff;text-decoration:none;border-radius:6px;">{{ __('mailing::mail.confirm.button') }}</a>
                            </p>
                            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;">{{ __('mailing::mail.confirm.valid', ['days' => $validDays]) }}</p>
                            <p style="margin:0;font-size:14px;color:#4b5563;">{{ __('mailing::mail.confirm.ignore') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
