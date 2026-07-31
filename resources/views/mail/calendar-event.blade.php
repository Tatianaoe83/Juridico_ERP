{{-- Plantilla mínima a propósito: el diseño va aparte. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Evento {{ $label }}</title>
</head>
<body>
    <h1>Evento {{ $label }}</h1>

    <p><strong>{{ $title }}</strong></p>

    @foreach ($details as $row)
        <p>{{ $row['label'] }}: {{ $row['value'] }}</p>
    @endforeach

    @if ($changes)
        <h2>Qué cambió</h2>

        <table>
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Antes</th>
                    <th>Ahora</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($changes as $change)
                    <tr>
                        <td>{{ $change['label'] }}</td>
                        <td>{{ $change['before'] }}</td>
                        <td>{{ $change['after'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($guests)
        <h2>Invitados</h2>

        <ul>
            @foreach ($guests as $guest)
                <li>{{ $guest['name'] }} — {{ $guest['response'] }}</li>
            @endforeach
        </ul>
    @endif

    @if ($url)
        <p><a href="{{ $url }}">Ver en Outlook</a></p>
    @endif

    <p>{{ config('app.name') }}</p>
</body>
</html>
