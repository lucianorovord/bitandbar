@extends('plantilla')
@section('title','Login')
@section('contenido')
    @vite('resources/sass/inicio.scss')
    <section class="hero">
        <p class="hero__kicker">Acceso</p>
        <h2 class="hero__title">Iniciar sesion</h2>
        <p class="hero__text">
            Accede a tu cuenta para gestionar tu plan de nutricion y entrenamiento.
        </p>
        <a class="hero__cta" href="#">Entrar</a>
    </section>
@endsection
