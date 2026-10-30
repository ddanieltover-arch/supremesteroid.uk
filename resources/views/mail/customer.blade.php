@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;">
        {{ $headline }}
    </h1>

    @if (! empty($orderNumber))
        <p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#475569;">
            Order <strong style="color:#0f172a;">{{ $orderNumber }}</strong>
        </p>
    @endif

    @foreach (($bodyLines ?? []) as $line)
        <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">
            {{ $line }}
        </p>
    @endforeach

    @if (empty($bodyLines) && ! empty($bodyText))
        <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">
            {{ $bodyText }}
        </p>
    @endif

    @if (! empty($actionUrl) && ! empty($actionLabel))
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 8px;">
            <tr>
                <td style="border-radius:8px;background-color:#1d4ed8;">
                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 20px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">
                        {{ $actionLabel }}
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
            Or open: <a href="{{ $actionUrl }}" style="color:#1d4ed8;text-decoration:none;">{{ $actionUrl }}</a>
        </p>
    @endif
@endsection
