<nav class="site-nav" aria-label="Navegacion principal">
    <a class="site-nav__link" href="{{ route('home') }}">Inicio</a>
    <a class="site-nav__link" href="{{ url('/recetas') }}">Recetas</a>

    @if (Route::has('login'))
        @auth
            <a class="site-nav__link" href="{{ route('profile.edit') }}">{{ Auth::user()->name }}</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="site-nav__link" style="background:none;border:none;cursor:pointer;padding:0;">Cerrar sesion</button>
            </form>
        @else
            <a class="site-nav__link" href="{{ route('login') }}">Iniciar sesion</a>
            @if (Route::has('register'))
                <a class="site-nav__link" href="{{ route('register') }}">Registrarse</a>
            @endif
        @endauth
    @endif
</nav>
