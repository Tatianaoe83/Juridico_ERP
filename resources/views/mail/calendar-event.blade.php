{{--
    Maquetada con tablas y estilos en línea a propósito: Outlook de escritorio
    renderiza con el motor de Word (sin flex ni grid) y Gmail descarta las
    hojas de estilo del <head>.
--}}
@php
    // El color y el símbolo cambian según la acción: se identifica de un
    // vistazo sin leer el asunto.
    //
    // Se usan glifos del bloque Dingbats y no emoji ni SVG: los emoji salen a
    // color y desentonan con el círculo, Gmail elimina el SVG en línea, y una
    // imagen obligaría a alojarla o a adjuntarla.
    [$accent, $glyph] = match ($action) {
        'created' => ['#459AF7', '✚'],   // cruz: alta
        'updated' => ['#D98A1F', '✎'],   // lápiz: edición
        'deleted' => ['#DC4C4C', '✕'],   // aspa: baja
    };

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
    <title>Evento {{ $label }}</title>
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
                        {{-- La pila de fuentes incluye las de símbolos de Windows:
                             sin ellas, Outlook de escritorio dibuja un cuadro. --}}
                        <td align="center" valign="middle" width="56" height="56" style="width:56px; height:56px; background-color:#FFFFFF; border-radius:28px; font-family:'Segoe UI Symbol','Apple Symbols','Segoe UI',Arial,sans-serif; font-size:24px; line-height:56px; color:{{ $accent }};">
                            {{ $glyph }}
                        </td>
                    </tr>
                </table>

                <p style="margin:18px 0 0; font-size:12px; letter-spacing:0.16em; text-transform:uppercase; color:#FFFFFF; opacity:0.85;">
                    Calendario · {{ config('app.name') }}
                </p>
                <h1 style="margin:6px 0 0; font-size:24px; line-height:1.25; font-weight:600; color:#FFFFFF;">
                    Evento {{ $label }}
                </h1>
            </td>
        </tr>

        {{-- Asunto del evento --}}
        <tr>
            <td align="center" style="padding:30px 32px 22px;">
                <p style="margin:0; font-size:20px; line-height:1.35; font-weight:600; color:{{ $ink }};">
                    {{ $title }}
                </p>
            </td>
        </tr>

        {{-- Ficha --}}
        @if ($details)
            <tr>
                <td style="padding:0 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F7F8FA; border-radius:10px;">
                        @foreach ($details as $row)
                            <tr>
                                <td style="padding:14px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid '.$line.';' }}">
                                    <p style="margin:0 0 3px; font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:{{ $muted }};">
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
        @endif

        {{-- Qué cambió: antes tachado y apagado, ahora resaltado --}}
        @if ($changes)
            <tr>
                <td style="padding:26px 32px 0;">
                    <p style="margin:0 0 12px; font-size:13px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; color:{{ $ink }};">
                        Qué cambió
                    </p>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid {{ $line }}; border-radius:10px;">
                        @foreach ($changes as $change)
                            <tr>
                                <td style="padding:16px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid '.$line.';' }}">
                                    <p style="margin:0 0 10px; font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:{{ $muted }};">
                                        {{ $change['label'] }}
                                    </p>

                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        {{-- Antes y ahora con el mismo peso y color: lo que
                                             distingue es la etiqueta y la barra lateral. --}}
                                        <tr>
                                            <td valign="top" style="padding:0 0 10px 12px; border-left:3px solid {{ $line }};">
                                                <p style="margin:0 0 2px; font-size:10px; letter-spacing:0.1em; text-transform:uppercase; color:{{ $muted }};">
                                                    Antes
                                                </p>
                                                <p style="margin:0; font-size:15px; line-height:1.45; font-weight:600; color:{{ $ink }};">
                                                    {{ $change['before'] }}
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td valign="top" style="padding:10px 0 0 12px; border-left:3px solid {{ $accent }};">
                                                <p style="margin:0 0 2px; font-size:10px; letter-spacing:0.1em; text-transform:uppercase; font-weight:700; color:{{ $accent }};">
                                                    Ahora
                                                </p>
                                                <p style="margin:0; font-size:15px; line-height:1.45; font-weight:600; color:{{ $ink }};">
                                                    {{ $change['after'] }}
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endif

        {{-- Invitados --}}
        @if ($guests)
            <tr>
                <td style="padding:26px 32px 0;">
                    <p style="margin:0 0 12px; font-size:13px; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; color:{{ $ink }};">
                        Invitados
                    </p>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        @foreach ($guests as $guest)
                            <tr>
                                <td style="padding:7px 0; {{ $loop->last ? '' : 'border-bottom:1px solid '.$line.';' }}">
                                    <span style="font-size:14px; color:{{ $ink }};">{{ $guest['name'] }}</span>
                                    <span style="font-size:12px; color:{{ $muted }};"> — {{ $guest['response'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endif

        {{-- Acción --}}
        @if ($url)
            <tr>
                <td align="center" style="padding:28px 32px 4px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="background-color:{{ $accent }}; border-radius:8px;">
                                <a href="{{ $url }}" style="display:inline-block; padding:13px 32px; font-size:15px; font-weight:600; color:#FFFFFF; text-decoration:none;">
                                    Ver en Outlook
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
                    Este aviso se envía automáticamente al crear, editar o eliminar un evento
                    del calendario compartido.
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
