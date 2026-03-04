<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Bit & Bar</title>
    @vite('resources/js/app.js')
    @yield('styles')
</head>
<body>
    <header>
        <div class="site-header">
            <h1>Bit & Bar</h1>
            @include('partials.nav')
        </div>
    </header>

    <main>
        @yield('contenido')
    </main>
</body>
</html>
