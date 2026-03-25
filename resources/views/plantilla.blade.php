<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Bit & Bar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite('resources/js/app.js')
    @vite('resources/sass/breeze-custom.scss')
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

    <div id="ws-global-card" class="ws-global-card" hidden>
        <div class="ws-global-card__inner" id="ws-global-card-inner">
            <div class="ws-global-card__left">
                <span class="ws-global-card__label">
                    <i class="bi bi-activity"></i> Sesion activa
                </span>
                <span class="ws-global-card__clock" id="ws-global-clock">
                    00:00
                </span>
            </div>
            <div class="ws-global-card__right">
                <span class="ws-global-card__name" id="ws-global-name">
                    Entrenamiento activo
                </span>
                <span class="ws-global-card__sets" id="ws-global-sets">
                    0 series completadas
                </span>
            </div>
            <button type="button" class="ws-global-card__cta" id="ws-global-cta">
                <i class="bi bi-play-fill"></i>
            </button>
        </div>

        <div class="ws-global-panel" id="ws-global-panel" hidden>
            <div class="ws-global-panel__handle"></div>
            <div class="ws-global-panel__header">
                <div>
                    <span class="ws-global-card__label">
                        <i class="bi bi-activity"></i> Sesion activa
                    </span>
                    <span class="ws-global-card__clock" id="ws-global-clock-panel">
                        00:00
                    </span>
                </div>
                <a href="{{ url('/entrenamiento/sesion?resume=1') }}" class="ws-global-panel__resume-btn">
                    <i class="bi bi-arrow-right-circle"></i> Ir a sesion
                </a>
            </div>
            <div class="ws-global-panel__exercises" id="ws-global-exercises"></div>
            <div class="ws-global-panel__footer">
                <button type="button" class="ws-global-panel__close" id="ws-global-panel-close">
                    <i class="bi bi-chevron-down"></i> Cerrar
                </button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/workout-global-card.js') }}"></script>
</body>
</html>
