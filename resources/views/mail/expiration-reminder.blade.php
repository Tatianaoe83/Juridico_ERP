{{--
    Misma maqueta que calendar-event: tablas y estilos en línea para Outlook
    de escritorio y Gmail. Ámbar mientras falta; rojo el día que vence.
--}}
@php
    $accent = $today ? '#DC4C4C' : '#D98A1F';
    $ink = '#1F2430';
    $muted = '#8A94A6';
    $line = '#E8EAED';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $remaining }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#F4F5F7; -webkit-font-smoothing:antialiased;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F5F7;">
<tr>
<td align="center" style="padding:32px 12px;">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#FFFFFF; border-radius:14px; overflow:hidden; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">

        {{-- Cabecera --}}
        <tr>
            <td align="center" style="background-color:{{ $accent }}; padding:32px 32px 28px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        {{-- Reloj de arena del bloque de símbolos: sin emoji ni imagen. --}}
                        <td align="center" valign="middle" width="56" height="56" style="width:56px; height:56px; background-color:#FFFFFF; border-radius:28px; font-family:'Segoe UI Symbol','Apple Symbols','Segoe UI',Arial,sans-serif; font-size:24px; line-height:56px; color:{{ $accent }};">
                            ⌛
                        </td>
                    </tr>
                </table>

                <p style="margin:18px 0 0; font-size:13px; color:#FFFFFF; opacity:0.85;">
                    {{ $kind }}
                </p>
                <h1 style="margin:6px 0 0; font-size:24px; line-height:1.25; font-weight:600; color:#FFFFFF;">
                    {{ $remaining }}
                </h1>
            </td>
        </tr>

        {{-- Qué vence --}}
        <tr>
            <td align="center" style="padding:30px 32px 22px;">
                <p style="margin:0; font-size:20px; line-height:1.35; font-weight:600; color:{{ $ink }};">
                    {{ $name }}
                </p>
            </td>
        </tr>

        {{-- Ficha --}}
        <tr>
            <td style="padding:0 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F7F8FA; border-radius:10px;">
                    @foreach ($details as $row)
                        <tr>
                            <td style="padding:14px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid '.$line.';' }}">
                                <p style="margin:0 0 3px; font-size:12px; color:{{ $muted }};">
                                    {{ $row['label'] }}
                                </p>
                                <p style="margin:0; font-size:15px; line-height:1.45; color:{{ $ink }};">
                                    {{ $row['value'] }}
                                </p>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>

        {{-- Acción --}}
        @if ($url)
            <tr>
                <td align="center" style="padding:28px 32px 4px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="background-color:{{ $accent }}; border-radius:8px;">
                                <a href="{{ $url }}" style="display:inline-block; padding:13px 32px; font-size:15px; font-weight:600; color:#FFFFFF; text-decoration:none;">
                                    Ver en el sistema
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        {{-- Pie --}}
        <tr>
            <td align="center" style="padding:30px 32px 32px;">
                <p style="margin:0; font-size:12px; line-height:1.6; color:{{ $muted }};">
                    Este es un correo automático, por favor no lo respondas.
                </p>
            </td>
        </tr>

    </table>

    <p style="margin:18px 0 0; font-size:11px; color:#A6AEBC; font-family:'Segoe UI', Helvetica, Arial, sans-serif;">
        {{ config('app.name') }}
    </p>

</td>
</tr>
</table>

</body>
</html>
