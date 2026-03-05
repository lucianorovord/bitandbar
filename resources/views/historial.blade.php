@extends('plantilla')
@section('title','Historial')
@section('contenido')
    @vite('resources/sass/historial.scss')
    @vite('resources/js/historial/index.js')

    <section class="hero historial-hero">
        <p class="hero__kicker">Historial</p>
        <h2 class="hero__title">Registro de comidas</h2>
        <p class="hero__text">Calendario mensual para visualizar tus registros de comida por dia.</p>
    </section>

    <section
        class="historial-panel"
        id="historial-calendar-panel"
        aria-label="Calendario de comidas"
        data-calendar-endpoint="{{ url('/historial/calendar-data') }}"
    >
        <header class="historial-calendar__header">
            <button type="button" class="mini-btn" id="historial-prev-month">Mes anterior</button>
            <h3 id="historial-month-label">{{ $calendar_payload['month_label'] }}</h3>
            <button type="button" class="mini-btn" id="historial-next-month">Mes siguiente</button>
        </header>

        <div class="historial-legend" aria-label="Leyenda">
            <span><i class="legend-dot legend-dot--green"></i> Dia con 2 o mas tipos de comida</span>
            <span><i class="legend-dot legend-dot--yellow"></i> Dia con 1 tipo de comida</span>
            <span><i class="legend-dot legend-dot--workout"></i> Dia con entrenamiento registrado</span>
            <span><i class="legend-dot legend-dot--none"></i> Sin registro</span>
        </div>

        <p class="historial-shortcuts">
            Atajos: <kbd>&larr;</kbd>/<kbd>&rarr;</kbd> mes | <kbd>Shift</kbd> + <kbd>&larr;</kbd>/<kbd>&rarr;</kbd> anio
        </p>

        <div class="historial-calendar" role="table" aria-label="Calendario mensual" id="historial-calendar">
            <div class="historial-calendar__weekdays" role="row">
                @foreach($week_days as $dayName)
                    <div role="columnheader">{{ $dayName }}</div>
                @endforeach
            </div>
            <div id="historial-calendar-weeks"></div>
        </div>

        <section class="historial-day-details" id="historial-day-details" hidden>
            <h4 id="historial-day-details-date">Detalle del dia</h4>
            <div class="historial-day-details__grid">
                <div>
                    <h5 class="historial-day-details__title">Comidas</h5>
                    <div id="historial-day-details-meals"></div>
                </div>
                <div>
                    <h5 class="historial-day-details__title">Entrenamientos</h5>
                    <div id="historial-day-details-workouts"></div>
                </div>
            </div>
        </section>
    </section>

    <script type="application/json" id="historial-calendar-initial">
        @json($calendar_payload)
    </script>
@endsection
