<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $storeName ?? 'Supreme Steroids' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background-color:#1d4ed8;padding:20px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    @if (! empty($logoUrl))
                                        <td width="48" valign="middle" style="padding-right:12px;">
                                            <img src="{{ $logoUrl }}" alt="{{ $storeName ?? 'Supreme Steroids' }}" width="40" height="40" style="display:block;border:0;border-radius:8px;background-color:#ffffff;">
                                        </td>
                                    @endif
                                    <td valign="middle">
                                        <p style="margin:0;font-size:18px;font-weight:700;letter-spacing:0.02em;color:#ffffff;">
                                            {{ $storeName ?? 'Supreme Steroids' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px 24px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
                            <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#475569;">
                                Questions? Email
                                <a href="mailto:{{ $supportEmail ?? 'info@supremesteroid.uk' }}" style="color:#1d4ed8;text-decoration:none;">
                                    {{ $supportEmail ?? 'info@supremesteroid.uk' }}
                                </a>
                            </p>
                            @if (! empty($businessAddress))
                                <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
                                    {{ $businessAddress }}
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
