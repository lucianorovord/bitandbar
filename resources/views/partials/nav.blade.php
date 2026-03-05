<nav class="site-nav" aria-label="Navegacion principal">
    <a class="site-nav__link" href="{{ route('home') }}">Inicio</a>
    <a class="site-nav__link" href="{{ url('/recetas') }}">Recetas</a>

    @if (Route::has('login'))
        @auth
            <a class="site-nav__link" href="{{ url('/comida/registrar') }}">Registrar comida</a>
            <a class="site-nav__link" href="{{ url('/entrenamiento/registrar') }}">Registrar entrenamiento</a>
            <a class="site-nav__link" href="{{ url('/historial') }}">Historial</a>

            <details class="profile-menu">
                <summary class="site-nav__link profile-menu__trigger">{{ Auth::user()->name }}</summary>
                <div class="profile-menu__dropdown">
                    <a class="site-nav__link profile-menu__item" href="{{ route('profile.edit') }}">Editar perfil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="site-nav__link profile-menu__item profile-menu__logout">Cerrar sesion</button>
                    </form>
                </div>
            </details>
        @else
            <a class="site-nav__link" href="{{ route('login') }}">Iniciar sesion</a>
            @if (Route::has('register'))
                <a class="site-nav__link" href="{{ route('register') }}">Registrarse</a>
            @endif
        @endauth
    @endif
</nav>
