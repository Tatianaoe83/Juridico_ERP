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

    @if ($when)
        <p>{{ $when }}</p>
    @endif

    @if ($location)
        <p>Ubicación: {{ $location }}</p>
    @endif

    @if ($url)
        <p><a href="{{ $url }}">Ver en Outlook</a></p>
    @endif

    <p>{{ config('app.name') }}</p>
</body>
</html>
